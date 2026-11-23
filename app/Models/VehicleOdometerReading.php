<?php

namespace App\Models;

use App\Enums\OdometerReadingSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma leitura do odômetro do veículo: quanto marcava, quando, por qual caminho e por quem.
 *
 * É a fonte da verdade **do odômetro**, não da OS nem do laudo. `maintenances.kilometers` continua
 * sendo o km daquela OS (o formulário edita, a API expõe, o PDF imprime) e esta linha é a cópia
 * dele — de mão única, como diz a migration.
 *
 * `source`, `counts_toward_odometer` e as duas FKs de origem **não são fillable**: só
 * App\Services\Vehicle\VehicleOdometerLog escreve. Fillable, qualquer `update()` de controller
 * marcaria uma autodeclaração como leitura de oficina, e é dessa distinção que depende o que pode
 * alimentar taxa e teto (ver .ai/rules/models.md).
 */
class VehicleOdometerReading extends Model
{
    protected $fillable = [
        'vehicle_id',
        'kilometers',
        'measured_at',
        'user_id',
        'workshop_id',
        'tenant_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_at' => 'date',
            'kilometers' => 'integer',
            'source' => OdometerReadingSource::class,
            'counts_toward_odometer' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function vehicleInspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * As leituras que entram no hodômetro do veículo. Par desta consulta em
     * Maintenance::scopeCountsTowardOdometer — mudar uma pede mudar a outra.
     */
    public function scopeCountsTowardOdometer(Builder $query): Builder
    {
        return $query->where('counts_toward_odometer', true);
    }

    /**
     * Da leitura mais recente para a mais antiga. `measured_at` é a data e empata dentro do mesmo
     * dia, então o desempate é explícito: quem foi gravado depois vence.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('measured_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}

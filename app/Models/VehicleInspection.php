<?php

namespace App\Models;

use App\Enums\InspectionVerdict;
use App\Observers\VehicleInspectionObserver;
use App\Support\Inspection\InspectionFindings;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

/**
 * Vistoria cautelar: o laudo de uma afirmação *sobre* o veículo, irmão de Maintenance, que é o
 * serviço feito *no* veículo. Ver .ai/rules/inspections.md para o porquê de ser tabela própria.
 *
 * `verdict`, `findings`, `verification_code`, `report_*`, as colunas de contestação e as de
 * supressão **não são fillable**: o veredito só é gravado por
 * App\Services\Inspection\VehicleInspectionService, que é quem recusa um "Aprovado" em cima de um
 * eixo reprovado. Fillable, qualquer `update()` de controller contornaria a garantia.
 */
#[ObservedBy(VehicleInspectionObserver::class)]
class VehicleInspection extends Model
{
    use HasFactory;

    /**
     * Prefixo do código público do laudo (LDO-XXXX-XX), irmão do RVL do Selo da oficina.
     */
    public const CODE_PREFIX = 'LDO';

    /**
     * Teto do resumo do laudo. O relatório inteiro é o PDF; o resumo é o que a tela mostra.
     */
    public const MAX_SUMMARY_LENGTH = 2000;

    /**
     * Teto do PDF do laudo, em kilobytes — o mesmo da nota fiscal da OS.
     */
    public const MAX_REPORT_KILOBYTES = 10240;

    protected $fillable = [
        'vehicle_id',
        'tenant_id',
        'workshop_id',
        'workshop_name',
        'user_id',
        'inspected_at',
        'odometer_km',
        'summary',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'date',
            'valid_until' => 'date',
            'verdict' => InspectionVerdict::class,
            'findings' => 'array',
            'owner_disputed_at' => 'datetime',
            'hidden_from_public_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function disputedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_disputed_by_user_id');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by_user_id');
    }

    /**
     * A trilha de supressão deste laudo, da mais antiga para a mais nova.
     *
     * `hiddenBy` acima e `hidden_reason` descrevem só a supressão **corrente**: devolver o laudo ao
     * histórico zera as duas, e sem esta relação não sobraria registro de que a supressão existiu.
     * É o que a tela do admin mostra (App\Services\Inspection\InspectionPublicVisibilityService).
     *
     * @return HasMany<InspectionVisibilityEvent, $this>
     */
    public function visibilityEvents(): HasMany
    {
        return $this->hasMany(InspectionVisibilityEvent::class, 'vehicle_inspection_id')->inOrder();
    }

    public function isDisputed(): bool
    {
        return $this->owner_disputed_at !== null;
    }

    public function isHiddenFromPublic(): bool
    {
        return $this->hidden_from_public_at !== null;
    }

    /**
     * Laudo vencido continua no histórico, marcado. Sumir com ele seria a alavanca que o produto
     * tirou do proprietário de propósito (ver "O proprietário contesta; esconder é da equipe" em
     * .ai/rules/models.md): histórico pertence ao chassi.
     */
    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /**
     * Eixos reprovados deste laudo.
     *
     * @return list<\App\Enums\InspectionFinding>
     */
    public function failingFindings(): array
    {
        return InspectionFindings::failing($this->findings);
    }

    /**
     * Eixos com algo a dizer (atenção ou reprovado): é o que a forma mínima mostra.
     *
     * @return list<\App\Enums\InspectionFinding>
     */
    public function reportableFindings(): array
    {
        return InspectionFindings::reportable($this->findings);
    }

    /**
     * O que o cartão e a faixa escrevem. Nunca "Selo da oficina": o selo afirma "eu fiz este
     * serviço", e o laudo afirma "eu examinei e atesto o que encontrei" — são afirmações
     * diferentes, e misturá-las dilui a única primitiva de confiança do produto.
     */
    public function getProvenanceLabelAttribute(): string
    {
        $verdict = $this->verdict instanceof InspectionVerdict
            ? $this->verdict->label()
            : 'Sem veredito';

        return 'Vistoria cautelar — '.$verdict;
    }

    public function getProvenanceSublabelAttribute(): string
    {
        return $this->workshop_name ?? $this->workshop?->name ?? 'Emissor não identificado';
    }

    public function verificationUrl(): ?string
    {
        if ($this->verification_code === null) {
            return null;
        }

        return url('/laudo/'.$this->verification_code);
    }

    /**
     * Fora da consulta pública e do histórico: suprimido pela equipe. Só a oficina emissora ainda o
     * vê — é ela quem corrige. Diferente de Maintenance::hiddenFrom, aqui não há o ramo do
     * `owner_decided_by_user_id`: o laudo não passa pelo fluxo de decisão de OS sem dono.
     */
    public function hiddenFrom(?User $viewer): bool
    {
        return $this->isHiddenFromPublic() && ! $this->isIssuedByWorkshopOf($viewer);
    }

    /**
     * Laudos que o histórico e a consulta pública mostram a este leitor (null = visitante).
     *
     * O par em memória é isVisibleInHistoryTo, e as duas precisam responder a mesma coisa: autorizar
     * um laudo pela rota de detalhe tem de deixar entrar exatamente quem o veria na listagem do
     * veículo. É a mesma exigência de Maintenance::scopeVisibleInHistoryTo (.ai/rules/policies.md).
     */
    public function scopeVisibleInHistoryTo(Builder $query, ?User $viewer): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $query) use ($table, $viewer): void {
            $query->whereNull($table.'.hidden_from_public_at');

            if ($viewer === null) {
                return;
            }

            if ($viewer->isWorkshop() && $viewer->workshop !== null) {
                $query->orWhere($table.'.workshop_id', $viewer->workshop->id);
            }
        });
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereNull($query->getModel()->getTable().'.hidden_from_public_at');
    }

    /**
     * Este laudo aparece no histórico que este leitor vê deste veículo? Forma em memória do scope
     * acima, mais a restrição de tenant de quem não passa em viewFullHistory.
     */
    public function isVisibleInHistoryTo(User $viewer): bool
    {
        if ($this->hiddenFrom($viewer)) {
            return false;
        }

        if ($viewer->tenant_id !== null && $this->tenant_id === $viewer->tenant_id) {
            return true;
        }

        // loadMissing, não $this->vehicle: a policy roda com o que o controller carregou, e o
        // preventLazyLoading fora de produção quebraria a autorização em vez de respondê-la.
        $this->loadMissing('vehicle');

        $vehicle = $this->getRelationValue('vehicle');

        return $vehicle instanceof Vehicle
            && Gate::forUser($viewer)->allows('viewFullHistory', $vehicle);
    }

    /**
     * Este leitor vê o laudo inteiro, ou só a forma mínima (App\Support\Inspection\InspectionRedactor)?
     *
     * A oficina emissora sempre vê o inteiro, mesmo sem vínculo com o veículo e mesmo com o laudo
     * suprimido: foi ela que escreveu a prosa e é ela quem corrige. Esconder dela o que ela assinou
     * seria absurdo — é a mesma razão por que MaintenanceRedactor abre tudo para a oficina autora.
     */
    public function showsFullReportTo(?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        if ($this->isIssuedByWorkshopOf($viewer)) {
            return true;
        }

        return $this->isVisibleInHistoryTo($viewer);
    }

    public function isIssuedByWorkshopId(?int $workshopId): bool
    {
        return $workshopId !== null && (int) $this->workshop_id === (int) $workshopId;
    }

    private function isIssuedByWorkshopOf(?User $viewer): bool
    {
        if ($viewer === null || ! $viewer->isWorkshop()) {
            return false;
        }

        return $this->isIssuedByWorkshopId($viewer->workshop?->id);
    }
}

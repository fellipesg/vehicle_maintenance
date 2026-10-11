<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cadastro da loja do lojista: CNPJ, contato e endereço. O CNPJ é o que separa estoque próprio de
 * consignação no assistente de veículo (App\Support\Vehicle\VehicleEntryFlow), lido sempre por
 * User::normalizedDocument().
 *
 * Uma linha por conta de garagem, criada por App\Services\TenantService ao abrir o tenant e mantida
 * em "Minha loja" (App\Http\Controllers\Web\Garage\ProfileController).
 */
class Garage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'document',
        'phone',
        'email',
        'cep',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Mesmo tratamento de users.document: guardado criptografado, nunca consultado por SQL.
            'document' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * CNPJ só com dígitos, para comparar com o documento do dono lido no CRLV-e.
     */
    public function normalizedDocument(): ?string
    {
        if ($this->document === null) {
            return null;
        }

        return preg_replace('/\D/', '', $this->document) ?: null;
    }

    /**
     * Endereço completo o bastante para aparecer numa tela (rua, número, cidade e UF).
     */
    public function hasAddress(): bool
    {
        return filled($this->street) && filled($this->number) && filled($this->city) && filled($this->state);
    }
}

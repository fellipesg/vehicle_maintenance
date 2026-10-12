<?php

namespace App\Services\Workshop;

use App\Enums\WorkshopProspectStatus;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopProspect;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Cadastro próprio de oficina: conta (user_type workshop), tenant e Workshop numa transação, pelo
 * mesmo TenantService das outras contas. Sem etapa de aprovação: o Workshop não tem esse campo e a
 * conta já nasce no plano gratuito (WorkshopPlan::Free).
 */
class WorkshopSignupService
{
    public function __construct(private TenantService $tenants) {}

    /**
     * @param  array<string, mixed>  $data  Dados validados por StoreWorkshopSignupRequest.
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'user_type' => 'workshop',
                'phone' => $data['phone'],
                'country' => 'Brasil',
            ]);

            $this->tenants->createForUser($user, [
                'name' => $data['trade_name'],
                'cnpj' => $data['cnpj'],
                'phone' => $data['phone'],
                'whatsapp' => $data['phone'],
                'cep' => $data['cep'],
                'street' => $data['street'],
                'number' => $data['number'],
                'complement' => $data['complement'] ?? null,
                'neighborhood' => $data['neighborhood'],
                'city' => $data['city'],
                'state' => $data['state'],
            ]);

            $this->convertProspects($user->workshop()->firstOrFail(), $data['cnpj'], $data['ref'] ?? null);

            return $user;
        });
    }

    /**
     * A prospecção converteu. O WorkshopObserver já pega a prospect de mesmo e-mail; aqui entram a
     * do link (ref) e a de mesmo CNPJ, que podem ter outro e-mail.
     */
    private function convertProspects(Workshop $workshop, string $cnpj, ?string $ref): void
    {
        WorkshopProspect::query()
            ->where('status', '!=', WorkshopProspectStatus::Converted)
            ->where(function ($query) use ($cnpj, $ref): void {
                $query->where('cnpj', $cnpj);

                if (filled($ref)) {
                    $query->orWhere('token', $ref);
                }
            })
            ->get()
            ->each(fn (WorkshopProspect $prospect) => $prospect->markConverted($workshop));
    }
}

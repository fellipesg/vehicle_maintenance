<?php

namespace App\Services\Workshop;

use App\Enums\WorkshopProspectStatus;
use App\Models\Identity;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopProspect;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro próprio de oficina: conta (user_type workshop), tenant e Workshop numa transação, pelo
 * mesmo TenantService das outras contas. Sem etapa de aprovação: o Workshop não tem esse campo e a
 * conta já nasce no plano gratuito (WorkshopPlan::Free).
 *
 * Quando o e-mail já tem identidade (a pessoa é proprietária e agora cadastra a oficina), a conta
 * nasce como irmã dela: mesma senha, mesmo 2FA, `tenant_id` próprio. A senha da identidade já foi
 * conferida pelo StoreWorkshopSignupRequest.
 */
class WorkshopSignupService
{
    public function __construct(private TenantService $tenants) {}

    /**
     * @param  array<string, mixed>  $data  Dados validados por StoreWorkshopSignupRequest.
     * @param  Identity|null  $identity  Identidade que já existe para o e-mail, quando existe.
     */
    public function register(array $data, ?Identity $identity = null): User
    {
        return DB::transaction(function () use ($data, $identity): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'identity_id' => $identity?->getKey(),
                // Conta irmã não mexe na senha da identidade; conta nova ganha a identidade dela
                // ao ser gravada (App\Models\User::save). A senha vai sem Hash::make porque o cast
                // `hashed` da identidade já cuida disso — e aceita um valor que **já** vem com hash,
                // que é como o cadastro em etapas entrega (nunca guardou a senha em claro).
                'password' => $identity === null ? ($data['password'] ?? null) : null,
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

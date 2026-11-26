<?php

namespace App\Services\User;

use App\Enums\Portal;
use App\Models\Identity;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;

/**
 * Criação da conta de proprietário: a conta, o tenant individual dela e nada mais, numa transação.
 *
 * Existe para as duas entradas do cadastro de proprietário — o /register de sempre e o cadastro em
 * etapas (/cadastro) — não terem duas implementações da mesma criação. Par do
 * App\Services\Workshop\WorkshopSignupService, que faz o mesmo para a oficina.
 *
 * `$identity` preenchida é conta irmã: o papel entra numa identidade que já existe, com a senha
 * dela intocada. Null é identidade nova, criada pelo App\Models\User ao gravar a conta.
 */
class OwnerSignupService
{
    public function __construct(private TenantService $tenants) {}

    /**
     * @param  array{name: string, email: string, password?: ?string, phone?: ?string, document?: ?string}  $data
     *                                                                                                             `password` pode vir em claro ou já com hash: o cast `hashed` da identidade aceita os dois.
     */
    public function register(array $data, ?Identity $identity = null): User
    {
        return DB::transaction(function () use ($data, $identity): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'identity_id' => $identity?->getKey(),
                'password' => $identity === null ? ($data['password'] ?? null) : null,
                'user_type' => Portal::Owner->value,
                'phone' => $data['phone'] ?? null,
                'document' => $data['document'] ?? null,
                'country' => 'Brasil',
            ]);

            $this->tenants->createForUser($user);

            return $user;
        });
    }
}

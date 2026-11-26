<?php

namespace Database\Factories;

use App\Enums\GarageStatus;
use App\Models\Garage;
use App\Models\Identity;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A conta nasce com a identidade dela (e-mail e senha), criada pelo próprio App\Models\User ao
 * gravar: a suíte inteira continua chamando `User::factory()` sem saber que a senha mudou de
 * tabela. Para contas irmãs — o mesmo e-mail em mais de um papel — use `forIdentity()`.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'user_type' => 'user',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->tenant_id) {
                return;
            }

            $type = match ($user->user_type) {
                'garage' => 'garage',
                'workshop' => 'workshop',
                default => 'individual',
            };

            $tenant = Tenant::factory()->create([
                'type' => $type,
                'name' => $user->name,
            ]);

            $user->update(['tenant_id' => $tenant->id]);

            if ($type === 'garage') {
                // Loja de teste nasce aprovada: a análise é o assunto de quem testa a fila, e
                // esses testes marcam o status na mão.
                // Espelha o TenantService: a loja nasce com uma cópia do contato da conta.
                Garage::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                ])->forceFill(['status' => GarageStatus::Approved])->save();
            }

            if ($type === 'workshop') {
                Workshop::factory()->forUser($user->fresh())->create([
                    'name' => $user->name,
                ]);
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function asUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'user',
        ]);
    }

    public function asGarage(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'garage',
        ]);
    }

    public function asWorkshop(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'workshop',
        ]);
    }

    public function asAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    /**
     * Conta irmã: o papel entra sob uma identidade que já existe, com o e-mail dela. A senha fica
     * como está — é da identidade, compartilhada com as outras contas, e um papel novo não a troca.
     */
    public function forIdentity(Identity $identity): static
    {
        return $this->state(fn (array $attributes): array => [
            'identity_id' => $identity->getKey(),
            'email' => $identity->email,
            'password' => null,
        ]);
    }
}

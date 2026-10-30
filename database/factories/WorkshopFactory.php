<?php

namespace Database\Factories;

use App\Enums\WorkshopStatus;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workshop>
 */
class WorkshopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asWorkshop(),
            'name' => fake()->company().' Mecânica',
            'phone' => fake()->numerify('11#########'),
            'whatsapp' => fake()->numerify('11#########'),
            'email' => fake()->companyEmail(),
            'cep' => fake()->numerify('########'),
            'street' => fake()->streetName(),
            'number' => (string) fake()->buildingNumber(),
            'neighborhood' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => 'SP',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Workshop $workshop) {
            if ($workshop->user_id && ! $workshop->tenant_id) {
                $user = User::find($workshop->user_id);
                if ($user?->tenant_id) {
                    $workshop->tenant_id = $user->tenant_id;
                }
            }

            // Oficina de teste nasce aprovada: a análise é o assunto de quem testa a fila, e esses
            // testes usam pending()/rejected() ou marcam o status na mão. status não é fillable,
            // então o valor é posto aqui, não no definition().
            if ($workshop->status === null) {
                $workshop->status = WorkshopStatus::Approved;
            }
        });
    }

    public function pending(): static
    {
        return $this->afterMaking(fn (Workshop $workshop) => $workshop->status = WorkshopStatus::Pending);
    }

    public function rejected(): static
    {
        return $this->afterMaking(fn (Workshop $workshop) => $workshop->status = WorkshopStatus::Rejected);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }
}

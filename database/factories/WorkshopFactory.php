<?php

namespace Database\Factories;

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
            'name' => fake()->company() . ' Mecânica',
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
        });
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }
}

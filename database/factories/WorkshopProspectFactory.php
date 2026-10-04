<?php

namespace Database\Factories;

use App\Enums\WorkshopProspectStatus;
use App\Models\WorkshopProspect;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkshopProspect>
 */
class WorkshopProspectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cnpj' => fake()->unique()->numerify('##############'),
            'trade_name' => fake()->company().' Auto Center',
            'legal_name' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('43#########'),
            'cnae' => '4520001',
            'street' => 'Rua das Flores',
            'number' => '100',
            'neighborhood' => 'Centro',
            'cep' => '86010000',
            'city' => 'LONDRINA',
            'state' => 'PR',
            'source' => 'receita_cnpj',
            'token' => Str::random(40),
            'status' => WorkshopProspectStatus::Pending,
        ];
    }

    public function sent(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => [
            'status' => WorkshopProspectStatus::Sent,
            'first_sent_at' => $at ?? now()->subDays(10),
        ]);
    }

    public function status(WorkshopProspectStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

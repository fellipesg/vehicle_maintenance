<?php

namespace Database\Factories;

use App\Models\Maintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Maintenance>
 */
class MaintenanceFactory extends Factory
{
    public function definition(): array
    {
        $maintenanceTypes = [
            'Revisão 10.000 km',
            'Revisão 20.000 km',
            'Revisão 30.000 km',
            'Troca de óleo',
            'Troca de pneus',
            'Alinhamento e balanceamento',
            'Revisão elétrica',
            'Pintura',
        ];

        $workshops = [
            'Oficina Central',
            'Auto Service',
            'Mecânica Express',
            'Oficina Premium',
            null,
        ];

        return [
            'vehicle_id' => \App\Models\Vehicle::factory(),
            'user_id' => \App\Models\User::factory(),
            'maintenance_type' => $this->faker->randomElement($maintenanceTypes),
            'description' => $this->faker->optional()->sentence(),
            'workshop_name' => $this->faker->randomElement($workshops),
            'maintenance_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'kilometers' => $this->faker->numberBetween(0, 200000),
            'service_category' => $this->faker->randomElement([
                'mechanical',
                'electrical',
                'suspension',
                'painting',
                'finishing',
                'interior',
                'other',
            ]),
            'is_manufacturer_required' => $this->faker->boolean(70),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Maintenance $maintenance) {
            if ($maintenance->user_id && ! $maintenance->tenant_id) {
                $user = \App\Models\User::find($maintenance->user_id);
                if ($user?->tenant_id) {
                    $maintenance->tenant_id = $user->tenant_id;
                }
            }
        });
    }

    public function sealedByWorkshop(): static
    {
        return $this->afterCreating(function (Maintenance $maintenance): void {
            $workshop = $maintenance->workshop_id
                ? \App\Models\Workshop::find($maintenance->workshop_id)
                : \App\Models\Workshop::factory()->create();

            $maintenance->forceFill([
                'workshop_id' => $workshop?->id,
                'workshop_name' => $workshop?->name ?? $maintenance->workshop_name,
                'registered_by_type' => 'workshop',
                'verified_at' => now(),
                'verified_workshop_id' => $workshop?->id,
                'verification_code' => 'RVL-'.strtoupper(fake()->unique()->bothify('????')).'-'.strtoupper(fake()->bothify('??')),
            ])->saveQuietly();
        });
    }

    public function declaredByOwner(): static
    {
        return $this->afterCreating(function (Maintenance $maintenance): void {
            $maintenance->forceFill([
                'registered_by_type' => 'owner',
                'verified_at' => null,
                'verified_workshop_id' => null,
                'verification_code' => null,
            ])->saveQuietly();
        });
    }

    public function declaredByGarage(): static
    {
        return $this->afterCreating(function (Maintenance $maintenance): void {
            $maintenance->forceFill([
                'registered_by_type' => 'garage',
                'verified_at' => null,
                'verified_workshop_id' => null,
                'verification_code' => null,
            ])->saveQuietly();
        });
    }
}

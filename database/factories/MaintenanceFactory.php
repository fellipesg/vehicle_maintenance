<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Maintenance>
 */
class MaintenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
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
}

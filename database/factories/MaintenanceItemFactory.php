<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceItem>
 */
class MaintenanceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $items = [
            'Óleo Motor',
            'Filtro de Óleo',
            'Filtro de Ar',
            'Filtro de Combustível',
            'Pastilhas de Freio',
            'Discos de Freio',
            'Amortecedor',
            'Pneu',
            'Bateria',
            'Correia Dentada',
        ];
        
        $quantity = $this->faker->numberBetween(1, 4);
        $unitPrice = $this->faker->randomFloat(2, 10, 500);
        
        return [
            'maintenance_id' => \App\Models\Maintenance::factory(),
            'name' => $this->faker->randomElement($items),
            'description' => $this->faker->optional()->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'part_number' => $this->faker->optional()->bothify('???-####'),
        ];
    }
}

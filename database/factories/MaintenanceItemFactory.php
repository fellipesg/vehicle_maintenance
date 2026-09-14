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
            'has_warranty' => false,
            'warranty_starts_at' => null,
            'warranty_ends_at' => null,
        ];
    }

    public function withWarranty(): static
    {
        return $this->state(function (array $attributes) {
            $startsAt = now()->subDays(10);

            return [
                'has_warranty' => true,
                'warranty_starts_at' => $startsAt,
                'warranty_ends_at' => $startsAt->copy()->addYear(),
            ];
        });
    }
}

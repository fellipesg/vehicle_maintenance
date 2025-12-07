<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_id' => \App\Models\Maintenance::factory(),
            'maintenance_item_id' => null,
            'invoice_type' => $this->faker->randomElement(['item', 'general']),
            'file_path' => 'invoices/' . $this->faker->uuid() . '.pdf',
            'file_name' => 'nota_fiscal_' . $this->faker->numerify('####') . '.pdf',
            'invoice_number' => $this->faker->optional()->numerify('########'),
            'invoice_date' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'total_amount' => $this->faker->optional()->randomFloat(2, 50, 2000),
        ];
    }
}

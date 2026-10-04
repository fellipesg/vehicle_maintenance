<?php

namespace Database\Factories;

use App\Models\WorkshopLead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkshopLead>
 */
class WorkshopLeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Auto Center';

        return [
            'name' => $name,
            'normalized_name' => WorkshopLead::normalizeName($name),
            'city' => fake()->city(),
            'state' => 'SP',
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->numerify('11#########'),
            'mentions_count' => 1,
            'last_mentioned_at' => now(),
        ];
    }
}

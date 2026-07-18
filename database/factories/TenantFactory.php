<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'individual',
            'name' => fake()->name(),
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => ['type' => 'individual']);
    }

    public function garage(): static
    {
        return $this->state(fn () => ['type' => 'garage']);
    }

    public function workshop(): static
    {
        return $this->state(fn () => ['type' => 'workshop']);
    }
}

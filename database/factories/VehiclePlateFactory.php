<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehiclePlate;
use App\Support\VehiclePlateSearch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehiclePlate>
 */
class VehiclePlateFactory extends Factory
{
    protected $model = VehiclePlate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'plate' => VehiclePlateSearch::normalize(strtoupper($this->faker->bothify('???#?##'))),
            'started_at' => null,
            'ended_at' => null,
            'source' => 'manual',
            'changed_by_user_id' => null,
            'tenant_id' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'ended_at' => now()->subMonths(3)->toDateString(),
            'started_at' => now()->subYears(2)->toDateString(),
        ]);
    }
}

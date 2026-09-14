<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Models\WorkshopMessageDispatch;
use App\Models\WorkshopMessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkshopMessageDispatch>
 */
class WorkshopMessageDispatchFactory extends Factory
{
    protected $model = WorkshopMessageDispatch::class;

    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'user_id' => User::factory()->asUser(),
            'vehicle_id' => Vehicle::factory(),
            'template_id' => WorkshopMessageTemplate::factory(),
            'dedupe_key' => (string) fake()->numberBetween(100_000, 200_000),
            'sent_at' => now(),
        ];
    }
}

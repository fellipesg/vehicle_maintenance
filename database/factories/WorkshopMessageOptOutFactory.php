<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopMessageOptOut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkshopMessageOptOut>
 */
class WorkshopMessageOptOutFactory extends Factory
{
    protected $model = WorkshopMessageOptOut::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asUser(),
            'workshop_id' => Workshop::factory(),
        ];
    }

    /**
     * Descadastro de todas as oficinas.
     */
    public function everyWorkshop(): self
    {
        return $this->state(fn (): array => ['workshop_id' => null]);
    }
}

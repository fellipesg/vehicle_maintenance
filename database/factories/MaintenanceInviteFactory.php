<?php

namespace Database\Factories;

use App\Models\Maintenance;
use App\Models\MaintenanceInvite;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceInvite>
 */
class MaintenanceInviteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'maintenance_id' => Maintenance::factory(),
            'workshop_id' => Workshop::factory(),
            'token' => MaintenanceInvite::newToken(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePhoto>
 */
class MaintenancePhotoFactory extends Factory
{
    protected $model = MaintenancePhoto::class;

    public function definition(): array
    {
        return [
            'maintenance_id' => Maintenance::factory(),
            'subject' => MaintenancePhoto::SUBJECT_VEHICLE,
            'stage' => MaintenancePhoto::STAGE_AFTER,
            'path' => 'maintenance-photos/'.fake()->uuid().'.jpg',
            'original_name' => 'photo.jpg',
            'sort' => 0,
            'created_by' => User::factory(),
        ];
    }
}

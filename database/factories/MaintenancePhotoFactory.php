<?php

namespace Database\Factories;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Support\AppStorage;
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

    /**
     * Foto já processada, no layout novo (`maintenance-photos/{maintenance_id}/{uuid}/...`). Não
     * grava arquivo: quem precisa do conteúdo usa Storage::fake e escreve os caminhos.
     */
    public function processed(): static
    {
        return $this->state(function (array $attributes): array {
            $directory = AppStorage::MAINTENANCE_PHOTOS_PREFIX.($attributes['maintenance_id'] instanceof Factory
                ? fake()->numberBetween(1, 9999)
                : $attributes['maintenance_id']).'/'.fake()->uuid();

            return [
                'path' => $directory.'/master.jpg',
                'thumb_path' => $directory.'/thumb.webp',
                'display_path' => $directory.'/display.webp',
                'width' => 2048,
                'height' => 1536,
                'bytes' => 250_000,
                'dominant_color' => '#6b7a8f',
                'processed_at' => now(),
            ];
        });
    }
}

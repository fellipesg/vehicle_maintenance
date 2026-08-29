<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePdfExport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehiclePdfExport>
 */
class VehiclePdfExportFactory extends Factory
{
    protected $model = VehiclePdfExport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'user_id' => User::factory(),
            'tenant_id' => null,
            'status' => VehiclePdfExport::STATUS_PENDING,
            'file_path' => null,
            'filename' => null,
            'error_message' => null,
            'expires_at' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => VehiclePdfExport::STATUS_COMPLETED,
            'file_path' => 'exports/vehicle-pdfs/test.pdf',
            'filename' => 'historico_manutencoes_TEST_2026-08-29.pdf',
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => VehiclePdfExport::STATUS_FAILED,
            'error_message' => 'Unable to generate PDF. Please try again later.',
            'completed_at' => now(),
        ]);
    }
}

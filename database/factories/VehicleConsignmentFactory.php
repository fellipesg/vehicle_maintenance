<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleConsignment>
 */
class VehicleConsignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'garage_user_id' => User::factory()->asGarage(),
            'tenant_id' => fn (array $attributes) => User::find($attributes['garage_user_id'])?->tenant_id,
            'owner_user_id' => null,
            'owner_name' => $this->faker->name(),
            'owner_email' => $this->faker->safeEmail(),
            'owner_phone' => $this->faker->numerify('(67) 9####-####'),
            'owner_document' => $this->faker->numerify('###########'),
            'declaration_accepted_at' => now(),
            'declaration_ip' => $this->faker->ipv4(),
            'declaration_user_agent' => 'Mozilla/5.0',
            'power_of_attorney_path' => null,
            'history_access_status' => VehicleConsignment::HISTORY_NONE,
            'status' => VehicleConsignment::STATUS_ACTIVE,
            'started_at' => now(),
        ];
    }

    public function withPowerOfAttorney(): static
    {
        return $this->state(fn (array $attributes) => [
            'power_of_attorney_path' => 'procuracoes/'.$this->faker->uuid().'.pdf',
            'history_access_status' => VehicleConsignment::HISTORY_PENDING,
        ]);
    }

    public function historyApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'history_access_status' => VehicleConsignment::HISTORY_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function ended(string $reason = 'sold'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VehicleConsignment::STATUS_ENDED,
            'ended_at' => now(),
            'end_reason' => $reason,
        ]);
    }
}

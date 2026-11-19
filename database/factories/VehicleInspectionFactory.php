<?php

namespace Database\Factories;

use App\Enums\InspectionFinding;
use App\Enums\InspectionFindingStatus;
use App\Enums\InspectionVerdict;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Support\Inspection\InspectionFindings;
use App\Support\Maintenance\VerificationCode;
use App\Support\VehicleTenantResolver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleInspection>
 */
class VehicleInspectionFactory extends Factory
{
    protected $model = VehicleInspection::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'workshop_id' => Workshop::factory(),
            'inspected_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'odometer_km' => fake()->numberBetween(10_000, 120_000),
            'summary' => fake()->optional()->sentence(),
            'valid_until' => null,
        ];
    }

    /**
     * `verdict`, `findings` e `verification_code` não são fillable (só
     * App\Services\Inspection\VehicleInspectionService grava), então os valores de teste entram por
     * forceFill aqui — antes do insert, porque veredito e achados são NOT NULL.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (VehicleInspection $inspection): void {
            if ($inspection->verdict === null) {
                $inspection->forceFill(['verdict' => InspectionVerdict::Approved]);
            }

            if ($inspection->findings === null) {
                $inspection->forceFill(['findings' => InspectionFindings::normalize([])]);
            }

            if ($inspection->verification_code === null) {
                $inspection->forceFill([
                    'verification_code' => VerificationCode::random(VerificationCode::INSPECTION_PREFIX),
                ]);
            }

            if ($inspection->workshop_name === null && is_numeric($inspection->workshop_id)) {
                $inspection->workshop_name = Workshop::find($inspection->workshop_id)?->name;
            }

            if ($inspection->tenant_id === null && is_numeric($inspection->vehicle_id)) {
                $vehicle = Vehicle::find($inspection->vehicle_id);
                $inspection->tenant_id = $vehicle === null
                    ? null
                    : VehicleTenantResolver::resolveTenantId($vehicle);
            }
        });
    }

    public function verdict(InspectionVerdict $verdict): static
    {
        return $this->afterMaking(fn (VehicleInspection $inspection) => $inspection->forceFill(['verdict' => $verdict]));
    }

    public function rejected(): static
    {
        return $this->verdict(InspectionVerdict::Rejected)
            ->failing(InspectionFinding::Identification);
    }

    public function withRestrictions(): static
    {
        return $this->verdict(InspectionVerdict::ApprovedWithRestrictions)
            ->finding(InspectionFinding::Paint, InspectionFindingStatus::Attention, 'Repintura na porta dianteira direita.');
    }

    public function failing(InspectionFinding $axis, ?string $note = null): static
    {
        return $this->finding($axis, InspectionFindingStatus::Fail, $note ?? 'Reprovado na vistoria.');
    }

    public function finding(InspectionFinding $axis, InspectionFindingStatus $status, ?string $note = null): static
    {
        return $this->afterMaking(function (VehicleInspection $inspection) use ($axis, $status, $note): void {
            $findings = InspectionFindings::normalize($inspection->findings);
            $findings[$axis->value] = ['status' => $status->value, 'note' => $note];

            $inspection->forceFill(['findings' => InspectionFindings::normalize($findings)]);
        });
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'inspected_at' => now()->subMonths(8)->format('Y-m-d'),
            'valid_until' => now()->subMonths(5)->format('Y-m-d'),
        ]);
    }

    public function hiddenByStaff(): static
    {
        return $this->afterCreating(function (VehicleInspection $inspection): void {
            $inspection->forceFill([
                'hidden_from_public_at' => now(),
                'hidden_by_user_id' => \App\Models\User::factory()->create(['is_admin' => true])->id,
                'hidden_reason' => 'Laudo com chassi divergente.',
            ])->saveQuietly();
        });
    }

    public function disputed(): static
    {
        return $this->afterCreating(function (VehicleInspection $inspection): void {
            $inspection->forceFill([
                'owner_disputed_at' => now(),
                'owner_dispute_reason' => 'Não reconheço esta vistoria.',
            ])->saveQuietly();
        });
    }
}

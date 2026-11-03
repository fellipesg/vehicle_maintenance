<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMileageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Teto de plausibilidade do hodômetro (VehicleMileageService::plausibilityCeiling).
 *
 * Antes dele, `max:9999999` era o único limite e nada impedia gravar 500.000 km num carro 2024 — o
 * número virava o current_kilometers do veículo e o piso de toda OS seguinte. O teto sai do ano do
 * veículo; ele não existe para quem não tem ano conhecido nem para quem já roda acima dele.
 */
class VehicleMileagePlausibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Base de 50.000 km + 100.000 km por ano de vida, com o ano-modelo contando inteiro. Vale para
     * veículo cuja rodagem atual não empurra o teto (ver plausibilityCeiling).
     */
    private function ceilingFor(int $year): int
    {
        return 50_000 + ((max(0, (int) now()->year - $year) + 1) * 100_000);
    }

    public function test_absurd_kilometers_are_refused_for_a_recent_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create([
            'year' => now()->year - 2,
            'current_kilometers' => 20_000,
            'odometer_at_registration' => 20_000,
        ]);

        $this->expectException(ValidationException::class);

        app(VehicleMileageService::class)->assertMaintenanceKilometers(
            $vehicle,
            500_000,
            now()->toDateString(),
        );
    }

    public function test_the_ceiling_itself_passes_and_one_kilometer_above_it_does_not(): void
    {
        $year = now()->year - 2;
        $ceiling = $this->ceilingFor($year);
        $vehicle = Vehicle::factory()->create([
            'year' => $year,
            'current_kilometers' => 0,
            'odometer_at_registration' => 0,
        ]);
        $service = app(VehicleMileageService::class);

        $service->assertMaintenanceKilometers($vehicle, $ceiling, now()->toDateString());

        try {
            $service->assertMaintenanceKilometers($vehicle, $ceiling + 1, now()->toDateString());
            $this->fail("Era esperada a recusa de {$ceiling} + 1 km.");
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                "no máximo {$ceiling} km",
                $exception->errors()['kilometers'][0],
            );
            $this->assertStringContainsString("veículo {$year}", $exception->errors()['kilometers'][0]);
        }
    }

    public function test_an_old_vehicle_with_high_mileage_is_not_blocked(): void
    {
        // 2004 com 380.000 km: rodagem alta, mas plausível para 20+ anos de estrada.
        $vehicle = Vehicle::factory()->create([
            'year' => 2004,
            'current_kilometers' => 370_000,
            'odometer_at_registration' => 370_000,
        ]);

        $service = app(VehicleMileageService::class);
        $service->assertMaintenanceKilometers($vehicle, 380_000, now()->toDateString());
        $service->applyMaintenanceKilometers($vehicle, 380_000);

        $this->assertSame(380_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_a_vehicle_that_already_runs_above_the_ceiling_keeps_accepting_records(): void
    {
        // Caminhão/frota recente: o hodômetro de cadastro já passa do teto derivado do ano. O teto
        // sobe para um ano de folga acima da rodagem atual, senão o veículo não somaria nem um km.
        $year = now()->year - 1;
        $alreadyRun = $this->ceilingFor($year) + 100_000;
        $vehicle = Vehicle::factory()->create([
            'year' => $year,
            'current_kilometers' => $alreadyRun,
            'odometer_at_registration' => $alreadyRun,
        ]);
        $service = app(VehicleMileageService::class);

        // A folga é de um ano, não ilimitada.
        try {
            $service->assertMaintenanceKilometers($vehicle, $alreadyRun + 100_001, now()->toDateString());
            $this->fail('Era esperada a recusa de mais de um ano de folga acima da rodagem atual.');
        } catch (ValidationException) {
            // esperado
        }

        $service->assertMaintenanceKilometers($vehicle, $alreadyRun + 40_000, now()->toDateString());
        $service->applyMaintenanceKilometers($vehicle, $alreadyRun + 40_000);

        $this->assertSame($alreadyRun + 40_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_a_vehicle_without_a_known_year_has_no_ceiling(): void
    {
        $vehicle = Vehicle::factory()->create([
            'year' => 0,
            'current_kilometers' => 0,
            'odometer_at_registration' => 0,
        ]);

        $service = app(VehicleMileageService::class);
        $service->assertMaintenanceKilometers($vehicle, 9_999_999, now()->toDateString());
        $service->applyMaintenanceKilometers($vehicle, 9_999_999);

        $this->assertSame(9_999_999, $vehicle->fresh()->current_kilometers);
    }

    public function test_api_store_refuses_implausible_kilometers(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = $this->recentVehicle();
        $this->attachVehicleToUser($user, $vehicle);

        $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 500_000,
            'service_category' => 'mechanical',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['kilometers']);

        $this->assertSame(20_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_api_update_refuses_implausible_kilometers(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = $this->recentVehicle();
        $this->attachVehicleToUser($user, $vehicle);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 25_000,
            'maintenance_date' => now()->toDateString(),
        ]);

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", [
            'kilometers' => 500_000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['kilometers']);

        $this->assertSame(25_000, (int) $maintenance->fresh()->kilometers);
    }

    public function test_owner_web_form_refuses_implausible_kilometers(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->recentVehicle();
        $this->attachVehicleToUser($owner, $vehicle);

        $this->actingAs($owner)
            ->post(route('user.maintenances.store'), [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Revisão',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 500_000,
                'service_category' => 'mechanical',
            ])
            ->assertSessionHasErrors('kilometers');

        $this->assertSame(0, Maintenance::query()->count());
    }

    public function test_workshop_web_form_refuses_implausible_kilometers(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->recentVehicle(['license_plate' => 'KMP1D23']);
        $this->attachVehicleToUser($owner, $vehicle);

        $workshopUser = User::factory()->asWorkshop()->create();

        $this->actingAs($workshopUser)
            ->post(route('workshop.maintenances.store'), [
                'license_plate' => 'KMP1D23',
                'maintenance_type' => 'Revisão',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 500_000,
                'service_category' => 'mechanical',
            ])
            ->assertSessionHasErrors('kilometers');

        $this->assertSame(0, Maintenance::query()->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recentVehicle(array $attributes = []): Vehicle
    {
        return Vehicle::factory()->create([
            'year' => now()->year - 2,
            'current_kilometers' => 20_000,
            'odometer_at_registration' => 20_000,
            ...$attributes,
        ]);
    }
}

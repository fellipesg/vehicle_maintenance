<?php

namespace Tests\Feature;

use App\Enums\OdometerReadingSource;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleOdometerReading;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `odometer:backfill-readings`: a passada que alcança o histórico anterior aos observers, e o
 * `--check` que é o portão da etapa seguinte.
 *
 * Os testes criam as linhas com os eventos de model desligados (`Model::withoutEvents`), que é a
 * única forma honesta de simular o que já estava no banco antes de a projeção existir — com os
 * observers ligados não há legado que montar.
 */
class BackfillOdometerReadingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_projects_the_kilometers_that_were_already_stored(): void
    {
        [$vehicle, $maintenance, $inspection] = $this->legacyVehicle();

        $this->assertSame(0, VehicleOdometerReading::query()->count());

        $this->artisan('odometer:backfill-readings')->assertSuccessful();

        $readings = $vehicle->odometerReadings()->get();
        $this->assertCount(3, $readings);

        $registration = $readings->firstWhere('source', OdometerReadingSource::Registration);
        $this->assertSame(40_000, $registration->kilometers);
        $this->assertTrue($registration->counts_toward_odometer);

        $fromMaintenance = $readings->firstWhere('maintenance_id', $maintenance->id);
        $this->assertSame(55_000, $fromMaintenance->kilometers);
        $this->assertSame('2026-05-04', $fromMaintenance->measured_at->toDateString());
        $this->assertTrue($fromMaintenance->counts_toward_odometer);

        $fromInspection = $readings->firstWhere('vehicle_inspection_id', $inspection->id);
        $this->assertSame(52_000, $fromInspection->kilometers);
        // O laudo entra no histórico e nunca no hodômetro, no backfill como em qualquer gravação.
        $this->assertFalse($fromInspection->counts_toward_odometer);
    }

    public function test_running_it_twice_corrects_instead_of_stacking(): void
    {
        [$vehicle] = $this->legacyVehicle();

        $this->artisan('odometer:backfill-readings')->assertSuccessful();
        $this->artisan('odometer:backfill-readings')->assertSuccessful();

        // Cada leitura é upsert pela origem: a segunda passada não duplica nada.
        $this->assertSame(3, $vehicle->odometerReadings()->count());
    }

    public function test_the_check_fails_before_the_backfill_and_passes_after(): void
    {
        $this->legacyVehicle();

        // Sem leitura nenhuma a projeção devolve zero e a fórmula antiga devolve 55.000: divergem,
        // e é por isso que o --check sai com erro — trocar o cálculo agora mudaria o número.
        $this->artisan('odometer:backfill-readings', ['--check' => true])
            ->expectsOutputToContain('divergem')
            ->assertFailed();

        $this->artisan('odometer:backfill-readings')->assertSuccessful();

        // Dois --check no mesmo processo: é este teste que trava o reset dos contadores no início
        // do handle (o console reaproveita a instância, e o primeiro --check deixava a divergência
        // dele somada no segundo).
        $this->artisan('odometer:backfill-readings', ['--check' => true])
            ->expectsOutputToContain('Nenhuma divergência')
            ->assertSuccessful();
    }

    public function test_the_check_writes_nothing(): void
    {
        $this->legacyVehicle();

        $this->artisan('odometer:backfill-readings', ['--check' => true])->assertFailed();

        $this->assertSame(0, VehicleOdometerReading::query()->count());
    }

    public function test_a_disputed_service_is_projected_without_counting_and_the_check_agrees(): void
    {
        [$vehicle, $maintenance] = $this->legacyVehicle();

        Model::withoutEvents(function () use ($maintenance): void {
            $maintenance->forceFill(['owner_disputed_at' => now()])->save();
        });

        $this->artisan('odometer:backfill-readings')->assertSuccessful();

        $reading = VehicleOdometerReading::query()->where('maintenance_id', $maintenance->id)->sole();

        // Contestado continua no histórico, fora da conta — a projeção lê a mesma regra do scope.
        $this->assertSame(55_000, $reading->kilometers);
        $this->assertFalse($reading->counts_toward_odometer);

        // E o hodômetro volta a ser o do cadastro, nas duas contas: é o que o --check verifica.
        $this->artisan('odometer:backfill-readings', ['--check' => true])->assertSuccessful();
        $this->assertSame(40_000, app(\App\Services\Vehicle\VehicleOdometerLog::class)
            ->highestCountedKilometers($vehicle->fresh()));
    }

    public function test_the_dry_run_counts_without_writing(): void
    {
        $this->legacyVehicle();

        $this->artisan('odometer:backfill-readings', ['--dry-run' => true])
            ->expectsOutputToContain('cadastro: 1, OS: 1, laudo: 1')
            ->expectsOutputToContain('Nada foi escrito')
            ->assertSuccessful();

        $this->assertSame(0, VehicleOdometerReading::query()->count());
    }

    public function test_check_and_dry_run_together_are_refused(): void
    {
        $this->artisan('odometer:backfill-readings', ['--check' => true, '--dry-run' => true])
            ->assertFailed();
    }

    public function test_the_limit_stops_the_run(): void
    {
        Model::withoutEvents(function (): void {
            Vehicle::factory()->count(3)->create([
                'year' => now()->year - 3,
                'current_kilometers' => 30_000,
                'odometer_at_registration' => 30_000,
            ]);
        });

        $this->artisan('odometer:backfill-readings', ['--limit' => 2])
            ->expectsOutputToContain('Parou no limite de 2')
            ->assertSuccessful();

        $this->assertSame(2, VehicleOdometerReading::query()->count());
    }

    public function test_a_single_vehicle_can_be_backfilled(): void
    {
        [$first] = $this->legacyVehicle();
        [$second] = $this->legacyVehicle('9BWZZZ377VT004252');

        $this->artisan('odometer:backfill-readings', ['--vehicle' => $first->id])->assertSuccessful();

        $this->assertSame(3, $first->odometerReadings()->count());
        $this->assertSame(0, $second->odometerReadings()->count());
    }

    /**
     * Um veículo como o banco o tinha antes da projeção: hodômetro de cadastro, uma OS com km e um
     * laudo, e nenhuma leitura.
     *
     * @return array{0: Vehicle, 1: Maintenance, 2: VehicleInspection}
     */
    private function legacyVehicle(string $chassis = '9BWZZZ377VT004251'): array
    {
        return Model::withoutEvents(function () use ($chassis): array {
            $workshopUser = User::factory()->asWorkshop()->create()->refresh();

            $vehicle = Vehicle::factory()->create([
                'chassis' => $chassis,
                'year' => now()->year - 4,
                'current_kilometers' => 55_000,
                'odometer_at_registration' => 40_000,
            ]);

            $maintenance = Maintenance::factory()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $workshopUser->id,
                'workshop_id' => $workshopUser->workshop->id,
                'kilometers' => 55_000,
                'maintenance_date' => '2026-05-04',
            ]);

            $inspection = VehicleInspection::factory()->create([
                'vehicle_id' => $vehicle->id,
                'workshop_id' => $workshopUser->workshop->id,
                'odometer_km' => 52_000,
                'inspected_at' => '2026-06-01',
            ]);

            return [$vehicle, $maintenance, $inspection];
        });
    }
}

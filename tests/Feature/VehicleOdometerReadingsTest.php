<?php

namespace Tests\Feature;

use App\Enums\OdometerReadingSource;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleOdometerReading;
use App\Services\Vehicle\VehicleOdometerLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tabela de leituras do odômetro (vehicle_odometer_readings) e o seu único escritor,
 * App\Services\Vehicle\VehicleOdometerLog.
 *
 * O veículo tem um odômetro, e o km dele estava em quatro colunas de três tabelas. Esta tabela é a
 * fonte da verdade **do odômetro** — não da OS nem do laudo — e recebe uma cópia de mão única de
 * cada escrita de km. Aqui está em teste o escritor, sozinho: quem o chama são os observers
 * (VehicleOdometerProjectionTest) e quem lê a tabela é o recálculo de `current_kilometers`
 * (VehicleOdometerFromReadingsTest).
 */
class VehicleOdometerReadingsTest extends TestCase
{
    use RefreshDatabase;

    private function log(): VehicleOdometerLog
    {
        return app(VehicleOdometerLog::class);
    }

    public function test_the_registration_reading_is_corrected_in_place_instead_of_stacking(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 50_000, 'odometer_at_registration' => 50_000]);

        $this->log()->recordRegistration($vehicle, 50_000, $owner);
        // A edição do veículo baixa o número: é a mesma leitura consertada, não uma leitura nova.
        $this->log()->recordRegistration($vehicle, 5_000, $owner);

        $readings = $vehicle->odometerReadings()->get();

        $this->assertCount(1, $readings);
        $this->assertSame(5_000, $readings->first()->kilometers);
        $this->assertSame(OdometerReadingSource::Registration, $readings->first()->source);
        $this->assertSame($vehicle->created_at->toDateString(), $readings->first()->measured_at->toDateString());
    }

    public function test_a_maintenance_reading_follows_the_kilometers_and_the_service_date(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->maintenance($owner, $vehicle, 61_000, '2026-03-10');

        $this->log()->recordFromMaintenance($maintenance);

        $reading = $this->maintenanceReadings($vehicle)->sole();
        $this->assertSame(61_000, $reading->kilometers);
        $this->assertSame('2026-03-10', $reading->measured_at->toDateString());
        $this->assertSame(OdometerReadingSource::Maintenance, $reading->source);
        $this->assertSame($maintenance->id, $reading->maintenance_id);

        // Correção da OS corrige a leitura dela, em vez de empilhar uma segunda. O unique em
        // maintenance_id é o que garante isso no banco, não só no código.
        $maintenance->update(['kilometers' => 62_500, 'maintenance_date' => '2026-04-02']);
        $this->log()->recordFromMaintenance($maintenance->fresh());

        $reading = $this->maintenanceReadings($vehicle)->sole();
        $this->assertSame(62_500, $reading->kilometers);
        $this->assertSame('2026-04-02', $reading->measured_at->toDateString());
    }

    public function test_a_maintenance_without_kilometers_has_no_reading(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->maintenance($owner, $vehicle, 61_000, '2026-03-10');
        $this->log()->recordFromMaintenance($maintenance);

        // maintenances.kilometers é nulável: OS sem km não é leitura de odômetro.
        $maintenance->update(['kilometers' => null]);

        $this->assertNull($this->log()->recordFromMaintenance($maintenance->fresh()));
        $this->assertSame(0, $this->maintenanceReadings($vehicle)->count());
    }

    public function test_a_disputed_or_suppressed_maintenance_stops_counting_without_losing_the_reading(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->maintenance($owner, $vehicle, 90_000, '2026-05-01');
        $this->log()->recordFromMaintenance($maintenance);
        $this->log()->recordRegistration($vehicle, 20_000, $owner);

        $this->assertSame(90_000, $this->log()->highestCountedKilometers($vehicle->fresh()));

        // Contestar não apaga o registro: ele continua em todas as superfícies, com a marca. O que
        // muda é parar de contar para o hodômetro (.ai/rules/models.md).
        $maintenance->owner_disputed_at = now();
        $maintenance->save();
        $this->log()->syncCounts($maintenance);

        $this->assertSame(2, $vehicle->odometerReadings()->count());
        $this->assertSame(20_000, $this->log()->highestCountedKilometers($vehicle->fresh()));

        // Retirar a contestação devolve.
        $maintenance->owner_disputed_at = null;
        $maintenance->save();
        $this->log()->syncCounts($maintenance);

        $this->assertSame(90_000, $this->log()->highestCountedKilometers($vehicle->fresh()));

        // Supressão pela equipe tira do mesmo jeito.
        $maintenance->hidden_by_user_id = $owner->id;
        $maintenance->save();
        $this->log()->syncCounts($maintenance);

        $this->assertSame(20_000, $this->log()->highestCountedKilometers($vehicle->fresh()));
    }

    public function test_the_inspection_reading_is_recorded_and_never_counts_toward_the_odometer(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();
        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'odometer_km' => 400_000,
            'inspected_at' => '2026-06-15',
        ]);

        $reading = $this->log()->recordFromInspection($inspection);

        $this->assertNotNull($reading);
        $this->assertSame(400_000, $reading->kilometers);
        $this->assertSame(OdometerReadingSource::Inspection, $reading->source);
        $this->assertFalse($reading->counts_toward_odometer);
        // O laudo pode estar relatando adulteração: alimentar o km do veículo com ele é o que
        // .ai/rules/inspections.md proíbe. O hodômetro fica onde estava — no km do cadastro.
        $this->assertSame(20_000, $this->log()->highestCountedKilometers($vehicle->fresh()));
    }

    public function test_an_owner_report_appends_a_reading_each_time(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $this->log()->recordOwnerReport($vehicle, 25_000, $owner);
        $this->log()->recordOwnerReport($vehicle, 25_150, $owner);

        $readings = $vehicle->odometerReadings()
            ->where('source', OdometerReadingSource::OwnerReport)
            ->latestFirst()
            ->get();

        // Série temporal: cada resposta do dono é leitura nova, não correção da anterior.
        $this->assertCount(2, $readings);
        $this->assertSame(25_150, $readings->first()->kilometers);
        $this->assertSame(OdometerReadingSource::OwnerReport, $readings->first()->source);
        $this->assertSame($owner->id, $readings->first()->user_id);
        $this->assertSame(25_150, $this->log()->highestCountedKilometers($vehicle->fresh()));
    }

    public function test_the_provenance_of_a_reading_is_not_mass_assignable(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();

        $reading = new VehicleOdometerReading([
            'vehicle_id' => $vehicle->id,
            'kilometers' => 10_000,
            'measured_at' => now()->toDateString(),
            'source' => OdometerReadingSource::Maintenance->value,
            'counts_toward_odometer' => false,
            'maintenance_id' => 123,
        ]);

        // Fillable, qualquer update() de controller marcaria autodeclaração como leitura de
        // oficina — e é dessa distinção que depende o que pode alimentar taxa e teto.
        $this->assertNull($reading->source);
        $this->assertNull($reading->maintenance_id);
        $this->assertNotFalse($reading->counts_toward_odometer);
    }

    public function test_a_reading_of_an_ownerless_workshop_record_keeps_a_null_tenant_and_still_counts(): void
    {
        $vehicle = Vehicle::factory()->create([
            'license_plate' => null,
            'renavam' => null,
            'current_kilometers' => 0,
            'odometer_at_registration' => null,
        ]);
        $workshopUser = User::factory()->asWorkshop()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $workshopUser->id,
            'kilometers' => 30_000,
            'maintenance_date' => '2026-02-01',
        ]);

        // MaintenanceFactory preenche o tenant a partir da conta; a OS de carro sem dono nasce com
        // tenant nulo (OwnerlessMaintenanceService::tenantIdFor), que é o caso em teste aqui.
        $maintenance->update(['tenant_id' => null]);

        $reading = $this->log()->recordFromMaintenance($maintenance->fresh());

        // O odômetro pertence ao chassi, não à conta: tenant nulo não tira a leitura da conta.
        $this->assertNull($reading->tenant_id);
        $this->assertTrue($reading->counts_toward_odometer);
        $this->assertSame(30_000, $this->log()->highestCountedKilometers($vehicle->fresh()));
    }

    /**
     * @return array{0: User, 1: Vehicle}
     */
    private function ownerWithVehicle(): array
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'year' => now()->year - 3,
            'current_kilometers' => 20_000,
            'odometer_at_registration' => 20_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner->refresh(), $vehicle];
    }

    /**
     * As leituras de OS deste veículo. O veículo da factory nasce com hodômetro de cadastro, que o
     * VehicleObserver projeta: quem fala de OS precisa dizer OS.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<VehicleOdometerReading, Vehicle>
     */
    private function maintenanceReadings(Vehicle $vehicle)
    {
        return $vehicle->odometerReadings()->where('source', OdometerReadingSource::Maintenance);
    }

    private function maintenance(User $owner, Vehicle $vehicle, int $kilometers, string $date): Maintenance
    {
        return Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'kilometers' => $kilometers,
            'maintenance_date' => $date,
        ]);
    }
}

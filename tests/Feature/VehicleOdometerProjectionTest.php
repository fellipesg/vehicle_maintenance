<?php

namespace Tests\Feature;

use App\Enums\OdometerReadingSource;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleOdometerReading;
use App\Services\FcmService;
use App\Services\Maintenance\MaintenanceOwnerDecisionService;
use App\Services\Maintenance\MaintenancePublicVisibilityService;
use App\Services\Vehicle\VehicleMileageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * Os ganchos que alimentam `vehicle_odometer_readings`: MaintenanceObserver, VehicleObserver e
 * VehicleInspectionObserver.
 *
 * Observer, e não chamada nos controllers, porque são seis superfícies gravando OS e três gravando
 * hodômetro de cadastro, com assinaturas diferentes — a projeção não pode depender de nenhuma delas
 * lembrar. Por isso os testes entram pelas portas reais (POST da oficina, serviço de contestação,
 * serviço de supressão) em vez de chamar o VehicleOdometerLog: o que está em teste é o gancho, e não
 * o escritor, que VehicleOdometerReadingsTest já cobre.
 *
 * Aqui está em teste só o enchimento da tabela. Quem a lê é
 * VehicleMileageService::refreshCurrentKilometers, e o efeito dela em `current_kilometers` é o
 * assunto de VehicleOdometerFromReadingsTest.
 */
class VehicleOdometerProjectionTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    private const PLATE = 'QOS6H54';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Notification::fake();
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->zeroOrMoreTimes();
    }

    public function test_a_service_registered_by_the_workshop_web_form_lands_in_the_readings(): void
    {
        $vehicle = $this->ownedCar();
        $workshopUser = $this->workshopAccount();

        $this->actingAs($workshopUser)->post('/oficina/manutencoes', [
            'license_plate' => self::PLATE,
            'maintenance_type' => 'Retífica de motor',
            'maintenance_date' => '2026-07-09',
            'kilometers' => 55_000,
            'service_category' => 'mechanical',
        ])->assertSessionHasNoErrors();

        $maintenance = Maintenance::query()->latest('id')->firstOrFail();
        $reading = $this->maintenanceReading($maintenance);

        $this->assertNotNull($reading);
        $this->assertSame(55_000, $reading->kilometers);
        $this->assertSame('2026-07-09', $reading->measured_at->toDateString());
        $this->assertSame(OdometerReadingSource::Maintenance, $reading->source);
        $this->assertTrue($reading->counts_toward_odometer);
        // Quem registrou vem junto, que é o ponto de ter histórico de leituras.
        $this->assertSame($workshopUser->workshop->id, $reading->workshop_id);
        $this->assertSame($workshopUser->id, $reading->user_id);
        $this->assertSame($vehicle->id, $reading->vehicle_id);
    }

    public function test_correcting_the_service_corrects_its_reading_instead_of_stacking_a_second(): void
    {
        $maintenance = $this->serviceOnOwnedCar(55_000, '2026-07-09');

        $maintenance->update(['kilometers' => 56_200, 'maintenance_date' => '2026-07-15']);

        $readings = VehicleOdometerReading::query()->where('maintenance_id', $maintenance->id)->get();

        $this->assertCount(1, $readings);
        $this->assertSame(56_200, $readings->first()->kilometers);
        $this->assertSame('2026-07-15', $readings->first()->measured_at->toDateString());
    }

    public function test_clearing_the_service_kilometers_removes_its_reading(): void
    {
        $maintenance = $this->serviceOnOwnedCar(55_000, '2026-07-09');
        $this->assertNotNull($this->maintenanceReading($maintenance));

        // `maintenances.kilometers` é nulável: OS sem km não é leitura de odômetro.
        $maintenance->update(['kilometers' => null]);

        $this->assertNull($this->maintenanceReading($maintenance));
    }

    public function test_the_owner_dispute_takes_the_reading_out_of_the_count_and_withdrawing_puts_it_back(): void
    {
        $vehicle = $this->ownedCar();
        $owner = $vehicle->currentOwner();
        $maintenance = $this->serviceOnOwnedCar(55_000, '2026-07-09', $vehicle);

        $this->assertTrue($this->maintenanceReading($maintenance)->counts_toward_odometer);

        // Pela porta real: o serviço de contestação não sabe que a tabela existe.
        app(MaintenanceOwnerDecisionService::class)->dispute($maintenance, $owner, 'Nunca levei aqui.');

        // Contestar marca, não apaga: a leitura continua lá, fora da conta (.ai/rules/models.md).
        $this->assertNotNull($this->maintenanceReading($maintenance));
        $this->assertFalse($this->maintenanceReading($maintenance)->counts_toward_odometer);

        app(MaintenanceOwnerDecisionService::class)->withdrawDispute($maintenance->fresh(), $owner);

        $this->assertTrue($this->maintenanceReading($maintenance)->counts_toward_odometer);
    }

    public function test_the_team_suppression_takes_the_reading_out_of_the_count_and_restoring_puts_it_back(): void
    {
        $maintenance = $this->serviceOnOwnedCar(55_000, '2026-07-09');
        $admin = User::factory()->create(['is_admin' => true]);

        app(MaintenancePublicVisibilityService::class)->hide($maintenance, $admin, 'Chassi divergente.');

        $this->assertFalse($this->maintenanceReading($maintenance)->counts_toward_odometer);

        app(MaintenancePublicVisibilityService::class)->restore($maintenance->fresh());

        $this->assertTrue($this->maintenanceReading($maintenance)->counts_toward_odometer);
    }

    public function test_deleting_the_service_deletes_its_reading(): void
    {
        $maintenance = $this->serviceOnOwnedCar(55_000, '2026-07-09');
        $id = $maintenance->id;

        $maintenance->delete();

        // Quem apaga é a FK em cascata, e é de propósito: a leitura de uma OS que não existe mais
        // não é histórico, é lixo que continuaria governando o hodômetro.
        $this->assertSame(0, VehicleOdometerReading::query()->where('maintenance_id', $id)->count());
    }

    public function test_a_vehicle_born_by_chassis_has_no_registration_reading_until_it_gets_an_odometer(): void
    {
        $vehicle = Vehicle::factory()->create([
            'license_plate' => null,
            'renavam' => null,
            'year' => now()->year - 2,
            'current_kilometers' => 0,
            'odometer_at_registration' => null,
        ]);

        // Veículo que a oficina criou só pelo chassi nasce sem hodômetro de cadastro: não há leitura.
        $this->assertNull($this->registrationReading($vehicle));

        app(VehicleMileageService::class)->registerOdometer($vehicle, 72_000, 'kilometers');

        $reading = $this->registrationReading($vehicle);
        $this->assertNotNull($reading);
        $this->assertSame(72_000, $reading->kilometers);
        $this->assertSame(OdometerReadingSource::Registration, $reading->source);
        $this->assertTrue($reading->counts_toward_odometer);
        // A coluna do cadastro não tem autor, e a leitura dela também não: quem cadastrou está no
        // vínculo, e o "quem registrou" é das origens que têm autor (ver VehicleObserver).
        $this->assertNull($reading->user_id);
    }

    public function test_lowering_the_vehicle_odometer_corrects_the_registration_reading_in_place(): void
    {
        $vehicle = Vehicle::factory()->create([
            'year' => now()->year - 2,
            'current_kilometers' => 90_000,
            'odometer_at_registration' => 90_000,
        ]);

        $this->assertSame(90_000, $this->registrationReading($vehicle)->kilometers);

        // É o que a edição do veículo faz quando o número baixa (UpdatesVehicleDetails): mesma
        // leitura consertada, não uma leitura nova.
        $vehicle->update(['current_kilometers' => 9_000, 'odometer_at_registration' => 9_000]);

        $readings = $vehicle->odometerReadings()->where('source', OdometerReadingSource::Registration)->get();

        $this->assertCount(1, $readings);
        $this->assertSame(9_000, $readings->first()->kilometers);
    }

    public function test_an_inspection_lands_in_the_readings_and_never_counts(): void
    {
        $vehicle = Vehicle::factory()->create([
            'year' => now()->year - 4,
            'current_kilometers' => 60_000,
            'odometer_at_registration' => 60_000,
        ]);

        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'odometer_km' => 58_000,
            'inspected_at' => '2026-08-20',
        ]);

        $reading = $this->inspectionReading($inspection);

        $this->assertNotNull($reading);
        $this->assertSame(58_000, $reading->kilometers);
        $this->assertSame('2026-08-20', $reading->measured_at->toDateString());
        $this->assertSame(OdometerReadingSource::Inspection, $reading->source);
        // A leitura abaixo do já registrado é justamente o achado da cautelar — entra no histórico,
        // e nunca no hodômetro (.ai/rules/inspections.md).
        $this->assertFalse($reading->counts_toward_odometer);

        $inspection->update(['odometer_km' => 57_500]);
        $this->assertSame(57_500, $this->inspectionReading($inspection)->kilometers);

        $inspection->update(['odometer_km' => null]);
        $this->assertNull($this->inspectionReading($inspection));
    }

    private function maintenanceReading(Maintenance $maintenance): ?VehicleOdometerReading
    {
        return VehicleOdometerReading::query()->where('maintenance_id', $maintenance->id)->first();
    }

    private function inspectionReading(VehicleInspection $inspection): ?VehicleOdometerReading
    {
        return VehicleOdometerReading::query()->where('vehicle_inspection_id', $inspection->id)->first();
    }

    private function registrationReading(Vehicle $vehicle): ?VehicleOdometerReading
    {
        return $vehicle->odometerReadings()
            ->where('source', OdometerReadingSource::Registration)
            ->first();
    }

    private function ownedCar(): Vehicle
    {
        $vehicle = Vehicle::factory()->create([
            'license_plate' => self::PLATE,
            'chassis' => '9BWZZZ377VT004999',
            'year' => now()->year - 5,
            'current_kilometers' => 10_000,
            'odometer_at_registration' => 10_000,
        ]);

        // Cadastro recuado: o hodômetro do cadastro é teto das OS anteriores à data de cadastro
        // (VehicleMileageService::maximumAllowedKilometers), e aqui o serviço é de julho.
        $vehicle->forceFill(['created_at' => '2026-01-10 09:00:00'])->saveQuietly();

        $this->ownerOf($vehicle, verified: true);

        return $vehicle->fresh();
    }

    private function serviceOnOwnedCar(int $kilometers, string $date, ?Vehicle $vehicle = null): Maintenance
    {
        $vehicle ??= $this->ownedCar();
        $workshopUser = $this->workshopAccount();

        $this->actingAs($workshopUser)->post('/oficina/manutencoes', [
            'license_plate' => (string) $vehicle->license_plate,
            'maintenance_type' => 'Retífica de motor',
            'maintenance_date' => $date,
            'kilometers' => $kilometers,
            'service_category' => 'mechanical',
        ])->assertSessionHasNoErrors();

        return Maintenance::query()->latest('id')->firstOrFail();
    }
}

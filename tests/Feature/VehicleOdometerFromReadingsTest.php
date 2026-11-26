<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Services\FcmService;
use App\Services\Maintenance\MaintenanceOwnerDecisionService;
use App\Services\Maintenance\MaintenancePublicVisibilityService;
use App\Services\Vehicle\VehicleMileageService;
use App\Services\Vehicle\VehicleOdometerLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * `vehicles.current_kilometers` sai de `vehicle_odometer_readings`.
 *
 * Até aqui a coluna era recalculada por uma fórmula — `max(odometer_at_registration, maior OS que
 * conta)` — escrita em dois métodos do VehicleMileageService. Agora ela é o maior km entre as
 * leituras que contam, e a tabela é a fonte da verdade do odômetro: a coluna é valor derivado,
 * cache de consulta.
 *
 * O que muda de verdade, e por isso está em teste aqui:
 *
 * - a autodeclaração do proprietário passa a mover o hodômetro (a fórmula antiga não a conhecia);
 * - veículo sem leitura gravada volta zero. É a condição que o `odometer:backfill-readings --check`
 *   existe para garantir antes de isto chegar à produção, e o último teste deste arquivo é essa
 *   condição escrita por extenso, para ninguém descobri-la em produção.
 */
class VehicleOdometerFromReadingsTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    private const PLATE = 'RLG4C21';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Notification::fake();
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->zeroOrMoreTimes();
    }

    public function test_the_odometer_is_the_highest_counted_reading(): void
    {
        $vehicle = $this->ownedCar();

        $this->serviceOnOwnedCar(55_000, '2026-07-09', $vehicle);

        $this->assertSame(55_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_a_service_below_the_odometer_does_not_pull_it_down(): void
    {
        $vehicle = $this->ownedCar();
        $this->serviceOnOwnedCar(55_000, '2026-07-09', $vehicle);

        // OS antiga, de km menor: entra no histórico e não mexe no hodômetro, porque o que vale é o
        // maior km entre as leituras, não o último gravado.
        $this->serviceOnOwnedCar(30_000, '2026-03-02', $vehicle);

        $this->assertSame(55_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_disputing_a_service_takes_the_odometer_back_down(): void
    {
        $vehicle = $this->ownedCar();
        $owner = $vehicle->currentOwner();
        $maintenance = $this->serviceOnOwnedCar(55_000, '2026-07-09', $vehicle);

        app(MaintenanceOwnerDecisionService::class)->dispute($maintenance, $owner, 'Nunca levei aqui.');

        // A OS contestada continua no histórico, com a marca, e para de contar: o hodômetro volta
        // ao km do cadastro. É a mesma regra de Maintenance::scopeCountsTowardOdometer, agora
        // chegando à coluna pela tabela de leituras.
        $this->assertSame(10_000, (int) $vehicle->fresh()->current_kilometers);

        app(MaintenanceOwnerDecisionService::class)->withdrawDispute($maintenance->fresh(), $owner);

        $this->assertSame(55_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_suppressing_a_service_takes_the_odometer_back_down(): void
    {
        $vehicle = $this->ownedCar();
        $maintenance = $this->serviceOnOwnedCar(55_000, '2026-07-09', $vehicle);
        $admin = User::factory()->asAdmin()->create();

        app(MaintenancePublicVisibilityService::class)->hide($maintenance, $admin, 'Chassi divergente.');

        $this->assertSame(10_000, (int) $vehicle->fresh()->current_kilometers);

        app(MaintenancePublicVisibilityService::class)->restore($maintenance->fresh(), $admin);

        $this->assertSame(55_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_an_inspection_never_moves_the_odometer(): void
    {
        $vehicle = $this->ownedCar();

        VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'odometer_km' => 400_000,
            'inspected_at' => '2026-06-15',
        ]);

        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());

        // O laudo pode estar relatando adulteração: a leitura dele entra no histórico com
        // counts_toward_odometer falso, e o hodômetro fica onde estava (.ai/rules/inspections.md).
        $this->assertSame(10_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_an_owner_report_moves_the_odometer(): void
    {
        $vehicle = $this->ownedCar();
        $owner = $vehicle->currentOwner();

        app(VehicleOdometerLog::class)->recordOwnerReport($vehicle, 42_000, $owner);
        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());

        // Comportamento novo: a fórmula antiga só olhava cadastro e OS, então o número que o
        // proprietário informa não tinha como chegar à coluna. É o que a notificação de odômetro vai
        // usar, e já funciona porque a coluna agora sai da tabela.
        $this->assertSame(42_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_a_vehicle_with_no_readings_keeps_its_kilometers_until_the_backfill_runs(): void
    {
        // Veículo e OS como estavam no banco antes de a projeção existir: sem observers, sem
        // leitura. withoutEvents é a única forma honesta de montar legado.
        [$vehicle, $maintenance] = Model::withoutEvents(function (): array {
            $vehicle = Vehicle::factory()->create([
                'license_plate' => 'LEG1A23',
                'chassis' => '9BWZZZ377VT004777',
                'year' => now()->year - 5,
                'current_kilometers' => 70_000,
                'odometer_at_registration' => 20_000,
            ]);

            $maintenance = Maintenance::factory()->create([
                'vehicle_id' => $vehicle->id,
                'kilometers' => 70_000,
                'maintenance_date' => '2026-05-04',
            ]);

            return [$vehicle, $maintenance];
        });

        $this->assertSame(0, $vehicle->odometerReadings()->count());

        // Projeção vazia não é odômetro zero: a tabela não tem o que dizer sobre este veículo, e
        // sobrescrever com zero apagaria o único lugar onde o número dele existe. O recálculo se
        // cala e espera o backfill.
        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());
        $this->assertSame(70_000, (int) $vehicle->fresh()->current_kilometers);

        // E o backfill é quem resolve: projeta o legado, o --check passa, e daí em diante a coluna
        // sai da tabela.
        $this->assertSame(0, Artisan::call('odometer:backfill-readings'));
        $this->assertSame(0, Artisan::call('odometer:backfill-readings', ['--check' => true]));

        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());
        $this->assertSame(70_000, (int) $vehicle->fresh()->current_kilometers);
        $this->assertSame(70_000, (int) $maintenance->fresh()->kilometers);
    }

    public function test_a_vehicle_whose_only_reading_stopped_counting_does_go_to_zero(): void
    {
        // A outra ponta da condição: aqui existe leitura, e ela diz zero. Zero por ausência de
        // resposta (o teste acima) e zero como resposta são coisas diferentes, e o recálculo
        // precisa saber qual é qual — senão o conserto do primeiro caso vira coluna travada.
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ZER0K99',
            'chassis' => '9BWZZZ377VT004666',
            'year' => now()->year - 5,
            'current_kilometers' => 30_000,
            'odometer_at_registration' => null,
        ]);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'kilometers' => 30_000,
            'maintenance_date' => '2026-04-01',
        ]);

        $this->assertSame(1, $vehicle->odometerReadings()->count());

        $maintenance->owner_disputed_at = now();
        $maintenance->save();

        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());

        $this->assertSame(1, $vehicle->odometerReadings()->count());
        $this->assertSame(0, (int) $vehicle->fresh()->current_kilometers);
    }

    private function ownedCar(): Vehicle
    {
        $vehicle = Vehicle::factory()->create([
            'license_plate' => self::PLATE,
            'chassis' => '9BWZZZ377VT004888',
            'year' => now()->year - 5,
            'current_kilometers' => 10_000,
            'odometer_at_registration' => 10_000,
        ]);

        // Cadastro recuado: o hodômetro do cadastro é teto das OS anteriores à data de cadastro
        // (VehicleMileageService::maximumAllowedKilometers), e aqui os serviços são de 2026.
        $vehicle->forceFill(['created_at' => '2026-01-10 09:00:00'])->saveQuietly();

        $this->ownerOf($vehicle, verified: true);

        return $vehicle->fresh();
    }

    private function serviceOnOwnedCar(int $kilometers, string $date, Vehicle $vehicle): Maintenance
    {
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

<?php

namespace Tests\Feature;

use App\Enums\OdometerReadingSource;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOdometerReading;
use App\Services\Vehicle\VehicleMileageService;
use App\Services\Vehicle\VehicleOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As três portas em que alguém digita o hodômetro fora de uma OS: a edição do veículo pela API, a
 * edição pela web e a chegada do proprietário a um veículo que a oficina criou pelo chassi.
 *
 * Todas gravavam `vehicles.current_kilometers` na mão. Depois que a coluna passou a ser derivada de
 * `vehicle_odometer_readings`, isso virou escrita que o próximo recálculo apagava — na API, na mesma
 * requisição. Agora as três gravam uma **leitura** (`OdometerReadingSource::OwnerReport`), e a
 * coluna sai dela como sai de qualquer outra.
 *
 * O que isso compra, e é o assunto dos testes daqui: o número que a pessoa digitou passa a existir
 * em algum lugar — então sobrevive ao recálculo, e não desaparece quando uma OS antiga chega depois.
 */
class VehicleOdometerOwnerReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_api_edit_keeps_the_kilometers_it_accepted(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = $this->ownedCar($user);

        $this->putJson("/api/v1/vehicles/{$vehicle->id}", ['current_kilometers' => 48_000])
            ->assertOk()
            ->assertJsonPath('data.current_kilometers', 48_000);

        // Antes isto respondia 20.000: a coluna era gravada e o recálculo a reescrevia na mesma
        // requisição, porque a edição não deixava leitura nenhuma.
        $this->assertSame(48_000, (int) $vehicle->fresh()->current_kilometers);

        $reading = $this->ownerReports($vehicle)->sole();
        $this->assertSame(48_000, $reading->kilometers);
        $this->assertSame($user->id, $reading->user_id);
    }

    public function test_the_api_edit_does_not_record_a_reading_when_the_number_did_not_change(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = $this->ownedCar($user);

        // O app reenvia o campo inteiro a cada edição. Trocar a cor não é leitura de odômetro, e
        // série temporal cheia de linhas idênticas é série que ninguém lê.
        $this->putJson("/api/v1/vehicles/{$vehicle->id}", [
            'color' => 'Cinza',
            'current_kilometers' => 20_000,
        ])->assertOk();

        $this->assertSame(0, $this->ownerReports($vehicle)->count());
        $this->assertSame('Cinza', $vehicle->fresh()->color);
    }

    public function test_the_api_edit_cannot_pull_the_odometer_below_a_recorded_service(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = $this->ownedCar($user);
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 60_000,
            'maintenance_date' => now()->toDateString(),
        ]);
        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());

        $this->putJson("/api/v1/vehicles/{$vehicle->id}", ['current_kilometers' => 30_000])->assertOk();

        // O relato entra no histórico, mas quem manda na coluna é o maior km que conta: a OS de
        // 60.000 continua lá, e o odômetro não anda para trás por autodeclaração.
        $this->assertSame(30_000, $this->ownerReports($vehicle)->sole()->kilometers);
        $this->assertSame(60_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_the_web_edit_survives_the_next_recalculation(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->ownedCar($owner);

        $this->actingAs($owner)
            ->put(route('user.vehicles.update', $vehicle), [
                'license_plate' => $vehicle->license_plate,
                'renavam' => $vehicle->renavam,
                'crv_number' => '264600365712',
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'chassis' => $vehicle->chassis,
                'current_kilometers' => 52_000,
            ])->assertSessionHasNoErrors();

        $this->assertSame(52_000, (int) $vehicle->fresh()->current_kilometers);

        // É isto que o número editado não tinha antes: lastro. Qualquer recálculo posterior
        // reescrevia a coluna pela fórmula e os 52.000 iam embora, porque não existiam em nenhuma
        // tabela.
        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());
        $this->assertSame(52_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_the_web_edit_that_lowers_the_number_corrects_the_registration_reading(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->ownedCar($owner);

        $this->actingAs($owner)
            ->put(route('user.vehicles.update', $vehicle), [
                'license_plate' => $vehicle->license_plate,
                'renavam' => $vehicle->renavam,
                'crv_number' => '264600365712',
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'chassis' => $vehicle->chassis,
                'current_kilometers' => 5_000,
            ])->assertSessionHasNoErrors();

        // Baixar o número conserta o hodômetro do cadastro, e a leitura do cadastro é corrigida no
        // lugar — é a mesma leitura consertada, não uma segunda.
        $this->assertSame(5_000, (int) $vehicle->fresh()->odometer_at_registration);
        $this->assertSame(
            5_000,
            $vehicle->odometerReadings()->where('source', OdometerReadingSource::Registration)->sole()->kilometers,
        );
        $this->assertSame(5_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_claiming_a_workshop_vehicle_records_the_declared_kilometers(): void
    {
        $owner = User::factory()->asUser()->create()->refresh();
        $workshopVehicle = Vehicle::factory()->create([
            'license_plate' => null,
            'renavam' => null,
            'chassis' => '9BWZZZ377VT004251',
            'year' => now()->year - 4,
            'current_kilometers' => 0,
            'odometer_at_registration' => null,
        ]);

        app(VehicleOwnershipService::class)->claimUnclaimedWorkshopVehicle($owner, $workshopVehicle, [
            'license_plate' => 'CHS1D23',
            'renavam' => '01050047521',
            'current_kilometers' => 30_000,
        ]);

        $reading = $this->ownerReports($workshopVehicle)->sole();
        $this->assertSame(30_000, $reading->kilometers);
        $this->assertSame($owner->id, $reading->user_id);
        $this->assertSame(30_000, (int) $workshopVehicle->fresh()->current_kilometers);

        // E o que o conserto compra: a OS antiga de km menor chega depois (aceita, porque sem
        // hodômetro de cadastro não há piso por data) e **não** derruba o que o dono declarou. Antes
        // os 30.000 estavam só na coluna, e o recálculo os substituía por 10.000.
        $workshopUser = User::factory()->asWorkshop()->create()->refresh();
        Maintenance::factory()->create([
            'vehicle_id' => $workshopVehicle->id,
            'user_id' => $workshopUser->id,
            'workshop_id' => $workshopUser->workshop->id,
            'kilometers' => 10_000,
            'maintenance_date' => now()->subYear()->toDateString(),
        ]);
        app(VehicleMileageService::class)->refreshCurrentKilometers($workshopVehicle->fresh());

        $this->assertSame(30_000, (int) $workshopVehicle->fresh()->current_kilometers);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<VehicleOdometerReading, Vehicle>
     */
    private function ownerReports(Vehicle $vehicle)
    {
        return $vehicle->odometerReadings()->where('source', OdometerReadingSource::OwnerReport);
    }

    private function ownedCar(User $owner): Vehicle
    {
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ODO1R23',
            'renavam' => '01050047521',
            'chassis' => '9BWZZZ377VT004333',
            'year' => now()->year - 4,
            'current_kilometers' => 20_000,
            'odometer_at_registration' => 20_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        return $vehicle->fresh();
    }
}

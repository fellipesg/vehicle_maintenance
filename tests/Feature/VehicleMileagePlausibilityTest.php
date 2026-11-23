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

    public function test_a_vehicle_at_the_top_of_its_year_range_still_gets_one_year_of_slack(): void
    {
        // Rodagem alta e plausível: o veículo chegou ao topo da faixa do ano dele. Parar exatamente
        // na leitura atual deixaria a faixa vazia — ele não somaria nem um km —, então a folga de um
        // ano continua valendo. Ela é um ano, não ilimitada, e não acumula por registro.
        $year = now()->year - 1;
        $alreadyRun = $this->ceilingFor($year);
        $vehicle = Vehicle::factory()->create([
            'year' => $year,
            'current_kilometers' => $alreadyRun,
            'odometer_at_registration' => $alreadyRun,
        ]);
        $service = app(VehicleMileageService::class);

        try {
            $service->assertMaintenanceKilometers($vehicle, $alreadyRun + 100_001, now()->toDateString());
            $this->fail('Era esperada a recusa de mais de um ano de folga acima do teto do ano.');
        } catch (ValidationException) {
            // esperado
        }

        $service->assertMaintenanceKilometers($vehicle, $alreadyRun + 40_000, now()->toDateString());
        $service->applyMaintenanceKilometers($vehicle, $alreadyRun + 40_000);

        $this->assertSame($alreadyRun + 40_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_a_vehicle_well_inside_its_year_range_gets_no_extra_slack(): void
    {
        // Veículo comum: o teto é o do ano, sem folga nenhuma por cima. A folga aparece só para quem
        // já está no topo da faixa, e é isso que mantém o teto apertado no caso que ele existe para
        // pegar — os 500.000 km num carro de dois anos.
        $year = now()->year - 2;
        $ceiling = $this->ceilingFor($year);
        $vehicle = Vehicle::factory()->create([
            'year' => $year,
            'current_kilometers' => 20_000,
            'odometer_at_registration' => 20_000,
        ]);
        $service = app(VehicleMileageService::class);

        $service->assertMaintenanceKilometers($vehicle, $ceiling, now()->toDateString());

        try {
            $service->assertMaintenanceKilometers($vehicle, $ceiling + 1, now()->toDateString());
            $this->fail("Era esperada a recusa de {$ceiling} + 1 km: veículo comum não ganha folga.");
        } catch (ValidationException $exception) {
            $this->assertStringContainsString("no máximo {$ceiling} km", $exception->errors()['kilometers'][0]);
        }
    }

    /**
     * A folga é uma, e é do ano: ela **não acumula por registro**. Era essa a catraca — cada leitura
     * gravada subia o teto em mais um ano de folga, então uma sequência de OS no teto levava o teto
     * daquele chassi a qualquer número (e, pelo cadastro sem teto, num único pedido).
     */
    public function test_the_slack_does_not_compound_across_records(): void
    {
        $year = now()->year;
        $fromYear = $this->ceilingFor($year);
        $widest = $fromYear + 100_000;
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'year' => $year,
            'current_kilometers' => 0,
            'odometer_at_registration' => 0,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);
        $service = app(VehicleMileageService::class);

        // Primeiro degrau: o teto do ano. Segundo: o ano de folga, que a leitura anterior liberou.
        foreach ([$fromYear, $widest] as $kilometers) {
            $service->assertMaintenanceKilometers($vehicle->fresh(), $kilometers, now()->toDateString());
            Maintenance::factory()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'kilometers' => $kilometers,
                'maintenance_date' => now()->toDateString(),
            ]);
            $service->applyMaintenanceKilometers($vehicle->fresh(), $kilometers);
        }

        $this->assertSame($widest, (int) $vehicle->fresh()->current_kilometers);

        // E para de subir aí: o terceiro degrau não existe.
        try {
            $service->assertMaintenanceKilometers($vehicle->fresh(), $widest + 1, now()->toDateString());
            $this->fail('Era esperada a recusa: a folga não acumula por registro.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString("no máximo {$widest} km", $exception->errors()['kilometers'][0]);
        }
    }

    /**
     * O defeito que o teto tinha: o termo "já rodado" vinha de current_kilometers e
     * odometer_at_registration, que nenhuma validação conferia. Um veículo do ano cadastrado com
     * 9.999.999 km levava o teto do próprio chassi para acima de qualquer `max:9999999`, e o teto
     * deixava de existir nas quatro superfícies de OS. Valor que nunca passaria na validação não
     * vira o piso que amplia a validação.
     */
    public function test_an_implausible_odometer_already_stored_does_not_widen_the_ceiling(): void
    {
        $year = now()->year - 2;
        $vehicle = Vehicle::factory()->create([
            'year' => $year,
            'current_kilometers' => 9_999_999,
            'odometer_at_registration' => 9_999_999,
        ]);
        $service = app(VehicleMileageService::class);
        $widest = $this->ceilingFor($year) + 100_000;

        // O km absurdo já gravado é o piso da data, então qualquer valor abaixo dele é recusado pelo
        // piso; o que importa aqui é que ele mesmo também é recusado, pelo teto — que ficou onde o
        // ano manda (no máximo um ano de folga acima dele) em vez de acompanhar o número gravado.
        foreach ([$widest + 1, 500_000] as $kilometers) {
            try {
                $service->assertMaintenanceKilometers($vehicle, $kilometers, now()->toDateString());
                $this->fail("Era esperada a recusa de {$kilometers} km.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('kilometers', $exception->errors());
            }
        }

        try {
            $service->assertMaintenanceKilometers($vehicle, 9_999_999, now()->toDateString());
            $this->fail('Era esperada a recusa de 9.999.999 km: o teto não acompanha o km absurdo já gravado.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString("no máximo {$widest} km", $exception->errors()['kilometers'][0]);
        }

        // E o veículo segue recusando OS nova até alguém corrigir o cadastro — a edição do veículo
        // aceita baixar a quilometragem, que é o caminho da correção.
        $service->assertOdometerKilometers(60_000, $year, $vehicle);
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

    public function test_workshop_web_form_refuses_implausible_kilometers_on_a_brand_new_chassis(): void
    {
        // Chassi que nasce nesta requisição: não há histórico para conferir, e a leitura vira o
        // hodômetro de cadastro do veículo (WorkshopVehicleRegistrar). O teto do ano informado vale.
        $workshopUser = User::factory()->asWorkshop()->create();

        $this->actingAs($workshopUser)
            ->post(route('workshop.maintenances.store'), [
                'chassis' => '9BWZZZ377VT004251',
                'new_vehicle' => [
                    'brand' => 'Honda',
                    'model' => 'Civic',
                    'year' => now()->year - 1,
                ],
                'maintenance_type' => 'Revisão',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 9_999_999,
                'service_category' => 'mechanical',
            ])
            ->assertSessionHasErrors(['kilometers' => 'A quilometragem deve ser no máximo '.($this->ceilingFor(now()->year - 1)).' km: é o teto plausível para um veículo '.(now()->year - 1).'. Confira se não sobrou um dígito.']);

        $this->assertSame(0, Maintenance::query()->count());
        $this->assertSame(0, Vehicle::query()->count());
    }

    public function test_register_odometer_refuses_implausible_kilometers(): void
    {
        $vehicle = Vehicle::factory()->create([
            'year' => now()->year - 2,
            'current_kilometers' => 0,
            'odometer_at_registration' => null,
        ]);

        try {
            app(VehicleMileageService::class)->registerOdometer($vehicle, 9_999_999);
            $this->fail('Era esperada a recusa do hodômetro de cadastro implausível.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_kilometers', $exception->errors());
        }

        $vehicle->refresh();
        $this->assertNull($vehicle->odometer_at_registration);
        $this->assertSame(0, $vehicle->current_kilometers);
    }

    public function test_api_vehicle_store_refuses_an_implausible_odometer(): void
    {
        $this->actingAsApiUser();

        $this->postJson('/api/v1/vehicles', $this->vehiclePayload(['current_kilometers' => 9_999_999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_kilometers']);

        $this->assertSame(0, Vehicle::query()->count());
    }

    public function test_api_vehicle_store_still_accepts_a_plausible_odometer(): void
    {
        $this->actingAsApiUser();

        $this->postJson('/api/v1/vehicles', $this->vehiclePayload(['current_kilometers' => 45_000]))
            ->assertCreated();

        $this->assertSame(45_000, (int) Vehicle::query()->sole()->odometer_at_registration);
    }

    public function test_api_vehicle_update_refuses_an_implausible_odometer(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = $this->recentVehicle();
        $this->attachVehicleToUser($user, $vehicle);

        $this->putJson("/api/v1/vehicles/{$vehicle->id}", ['current_kilometers' => 9_999_999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_kilometers']);

        $this->assertSame(20_000, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_web_vehicle_registration_refuses_an_implausible_odometer(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->post(route('user.vehicles.store'), $this->webVehiclePayload(['current_kilometers' => 9_999_999]))
            ->assertSessionHasErrors('current_kilometers');

        $this->assertSame(0, Vehicle::query()->count());
    }

    public function test_web_vehicle_registration_still_accepts_a_plausible_odometer(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->post(route('user.vehicles.store'), $this->webVehiclePayload(['current_kilometers' => 85_000]))
            ->assertSessionHasNoErrors();

        $vehicle = Vehicle::query()->sole();
        $this->assertSame(85_000, (int) $vehicle->odometer_at_registration);
        $this->assertTrue($owner->vehicles()->whereKey($vehicle->id)->exists());
    }

    public function test_web_vehicle_edit_refuses_an_implausible_odometer(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->recentVehicle();
        $this->attachVehicleToUser($owner, $vehicle);

        $this->actingAs($owner)
            ->put(route('user.vehicles.update', $vehicle), [
                'license_plate' => $vehicle->license_plate,
                'renavam' => $vehicle->renavam,
                'crv_number' => '264600365712',
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'chassis' => $vehicle->chassis,
                'current_kilometers' => 9_999_999,
            ])
            ->assertSessionHasErrors('current_kilometers');

        $this->assertSame(20_000, (int) $vehicle->fresh()->current_kilometers);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function vehiclePayload(array $attributes = []): array
    {
        return [
            'license_plate' => 'KMP1D23',
            'renavam' => '01050047521',
            'crv_number' => '264600365712',
            'chassis' => '9BWZZZ377VT004251',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => now()->year - 2,
            'current_kilometers' => 45_000,
            'terms_accepted' => true,
            ...$attributes,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function webVehiclePayload(array $attributes = []): array
    {
        return $this->vehiclePayload(['terms_accepted' => '1', ...$attributes]);
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

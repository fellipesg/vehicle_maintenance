<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\WorkshopRecordRegisteredNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * Oficina aprovada registra OS em qualquer veículo e o Selo da oficina sai na hora, inclusive em
 * carro que já tem proprietário cadastrado. O histórico pertence ao chassi e é o ativo do produto:
 * ele não espera a aprovação de ninguém, e o silêncio de um terceiro não vira veto.
 *
 * Este arquivo substituiu WorkshopSealRequiresLinkTest, que guardava a regra oposta (selo só com
 * vínculo provado). A regra caiu porque cobrava o preço no caso legítimo — oficina credenciada
 * atendendo cliente novo — e o risco que ela endereçava passou a ser coberto pela análise do
 * cadastro da oficina (EnsureWorkshopApproved: CNPJ conferido, conta que se perde) somada ao aviso
 * ao proprietário. O que sobreviveu e segue testado aqui: o selo sai na hora em carro de terceiro, o
 * dono é avisado, e data futura é recusada.
 */
class WorkshopSealOnThirdPartyVehicleTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    private const PLATE = 'QOS6H54';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->zeroOrMoreTimes();
    }

    public function test_workshop_seals_on_a_vehicle_that_already_has_an_owner(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownedVehicle();

        $this->actingAs($this->workshopAccount())
            ->post('/oficina/manutencoes', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $maintenance = Maintenance::firstOrFail();

        $this->assertNotNull($maintenance->verified_at, 'O selo não espera o proprietário.');
        $this->assertNotNull($maintenance->verification_code);
        $this->assertSame('workshop', $maintenance->registered_by_type);
        // Carro com dono: o registro entra no histórico dele direto, sem pendência de decisão.
        $this->assertNull($maintenance->owner_status);
        $this->assertSame($owner->tenant_id, $maintenance->tenant_id);
        $this->assertSame(55_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_the_seal_is_public_right_away(): void
    {
        Notification::fake();

        $this->ownedVehicle();
        $this->actingAs($this->workshopAccount())->post('/oficina/manutencoes', $this->payload());

        $maintenance = Maintenance::firstOrFail();

        $this->get('/v/'.$maintenance->verification_code)
            ->assertOk()
            ->assertSee('Selo da oficina confirmado');
    }

    public function test_workshop_still_seals_on_a_vehicle_without_an_owner(): void
    {
        Notification::fake();

        $vehicle = $this->ownerlessVehicle();

        $this->actingAs($this->workshopAccount())
            ->post('/oficina/manutencoes', [
                'chassis' => self::OWNERLESS_CHASSIS,
                'maintenance_type' => 'Revisão geral',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 45_000,
                'service_category' => 'mechanical',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $maintenance = Maintenance::firstOrFail();

        $this->assertNotNull($maintenance->verified_at);
        // Sem dono, o registro espera quem assumir o veículo (fluxo de convite e decisão).
        $this->assertSame(Maintenance::OWNER_PENDING, $maintenance->owner_status);
        $this->assertNull($maintenance->tenant_id);
        $this->assertSame(45_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_the_owner_is_told_a_workshop_wrote_on_their_car(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownedVehicle();

        $this->actingAs($this->workshopAccount())->post('/oficina/manutencoes', $this->payload());

        Notification::assertSentTo(
            $owner,
            WorkshopRecordRegisteredNotification::class,
            fn (WorkshopRecordRegisteredNotification $n): bool => $n->maintenance->vehicle_id === $vehicle->id,
        );
    }

    public function test_future_maintenance_date_is_refused(): void
    {
        Notification::fake();

        $this->ownedVehicle();

        $this->actingAs($this->workshopAccount())
            ->post('/oficina/manutencoes', $this->payload([
                'maintenance_date' => now()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('maintenance_date');

        $this->assertSame(0, Maintenance::query()->count());
    }

    public function test_the_api_seals_and_refuses_a_future_date(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownedVehicle();
        $workshopUser = $this->workshopAccount();

        $this->actingAs($workshopUser)
            ->postJson('/api/v1/maintenances', [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Revisão geral',
                'maintenance_date' => now()->addMonth()->toDateString(),
                'kilometers' => 55_000,
                'service_category' => 'mechanical',
            ])
            ->assertStatus(422);

        $this->actingAs($workshopUser)
            ->postJson('/api/v1/maintenances', [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Revisão geral',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 55_000,
                'service_category' => 'mechanical',
            ])
            ->assertStatus(201);

        $maintenance = Maintenance::firstOrFail();

        $this->assertNotNull($maintenance->verified_at);
        $this->assertNull($maintenance->owner_status);
        Notification::assertSentTo($owner, WorkshopRecordRegisteredNotification::class);
    }

    /**
     * @return array{0: User, 1: Vehicle}
     */
    private function ownedVehicle(): array
    {
        $vehicle = Vehicle::factory()->create([
            'license_plate' => self::PLATE,
            'chassis' => '9BWZZZ377VT004999',
            'current_kilometers' => 10_000,
            'odometer_at_registration' => 10_000,
        ]);

        return [$this->ownerOf($vehicle), $vehicle];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'license_plate' => self::PLATE,
            'maintenance_type' => 'Revisão geral',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 55_000,
            'service_category' => 'mechanical',
        ];
    }
}

<?php

namespace Tests\Feature\Maintenance;

use App\Enums\WorkshopReviewStatus;
use App\Exceptions\SelfAttestationException;
use App\Http\Controllers\Web\Workshop\ReviewController;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\Workshop;
use App\Services\Maintenance\MaintenanceVerificationStamper;
use App\Services\Maintenance\WorkshopReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * O invariante de conflito de interesse do Selo da oficina: ninguém sela o próprio carro.
 *
 * O selo é a premissa de confiança do produto — ele afirma que **uma oficina independente** atestou o
 * serviço. Cada teste aqui tem o par: o caminho conflitado (negado) e o caminho legítimo de terceiro
 * (intacto), porque uma guarda que também barra o caso central não é guarda, é regressão — foi
 * exatamente o que derrubou o `WorkshopVehicleLink` (.ai/rules/models.md, "O selo não espera o
 * proprietário").
 */
class SealIssuerIndependenceTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceVerificationStamper $stamper;

    private User $workshopUser;

    private Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stamper = app(MaintenanceVerificationStamper::class);
        // UserFactory::configure() já cria a oficina aprovada da conta.
        $this->workshopUser = User::factory()->asWorkshop()->create()->fresh();
        $this->workshop = $this->workshopUser->workshop;
    }

    // ── stamp: o caminho principal ───────────────────────────────────────────

    public function test_workshop_does_not_seal_a_service_on_a_vehicle_its_own_tenant_owns(): void
    {
        $vehicle = $this->vehicleOwnedByWorkshopTenant();
        $maintenance = $this->workshopMaintenance($vehicle);

        $stamped = $this->stamper->stamp($maintenance, $this->workshopUser);

        $this->assertNull($stamped->verified_at);
        $this->assertNull($stamped->verified_workshop_id);
        $this->assertNull($stamped->verification_code);
        // O serviço existiu e o histórico pertence ao chassi: o registro fica, como declarada.
        $this->assertSame('owner', $stamped->registered_by_type);
        $this->assertDatabaseHas('maintenances', ['id' => $maintenance->id]);
    }

    public function test_workshop_still_seals_a_service_on_a_third_party_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);
        $maintenance = $this->workshopMaintenance($vehicle);

        $stamped = $this->stamper->stamp($maintenance, $this->workshopUser);

        $this->assertNotNull($stamped->verified_at);
        $this->assertSame($this->workshop->id, $stamped->verified_workshop_id);
        $this->assertSame('workshop', $stamped->registered_by_type);
        $this->assertStringStartsWith('RVL-', (string) $stamped->verification_code);
    }

    public function test_workshop_still_seals_a_service_on_a_vehicle_with_no_owner_at_all(): void
    {
        // Carro sem dono é o caso central do produto: o selo não espera o proprietário.
        $maintenance = $this->workshopMaintenance(Vehicle::factory()->create());

        $stamped = $this->stamper->stamp($maintenance, $this->workshopUser);

        $this->assertNotNull($stamped->verified_at);
        $this->assertSame($this->workshop->id, $stamped->verified_workshop_id);
    }

    public function test_unverified_ownership_of_the_workshop_tenant_still_blocks_the_seal(): void
    {
        // attachVehicleToUser deixa ownership_verified_at nulo: posse declarada e não comprovada.
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($this->workshopUser, $vehicle);
        $this->assertNull(
            $this->workshopUser->vehicles()->firstWhere('vehicles.id', $vehicle->id)?->pivot->ownership_verified_at,
        );

        $stamped = $this->stamper->stamp($this->workshopMaintenance($vehicle), $this->workshopUser);

        $this->assertNull($stamped->verified_at);
    }

    public function test_a_former_owner_in_the_workshop_tenant_does_not_block_the_seal(): void
    {
        // Só a posse **atual** conflita: a oficina que vendeu o carro volta a ser terceiro nele.
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($this->workshopUser, $vehicle);
        $this->workshopUser->vehicles()->updateExistingPivot($vehicle->id, [
            'is_current_owner' => false,
            'sale_date' => now(),
        ]);
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        $stamped = $this->stamper->stamp($this->workshopMaintenance($vehicle), $this->workshopUser);

        $this->assertNotNull($stamped->verified_at);
    }

    public function test_an_active_consignment_of_the_workshop_tenant_blocks_the_seal(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleConsignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'tenant_id' => $this->workshopUser->tenant_id,
            'status' => VehicleConsignment::STATUS_ACTIVE,
        ]);

        $stamped = $this->stamper->stamp($this->workshopMaintenance($vehicle), $this->workshopUser);

        $this->assertNull($stamped->verified_at);
    }

    public function test_a_consignment_of_another_tenant_does_not_block_the_seal(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleConsignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => VehicleConsignment::STATUS_ACTIVE,
        ]);

        $stamped = $this->stamper->stamp($this->workshopMaintenance($vehicle), $this->workshopUser);

        $this->assertNotNull($stamped->verified_at);
    }

    public function test_the_auto_verify_shortcut_cannot_bypass_the_guard(): void
    {
        // O atalho de QA sela toda declarada que cita oficina; nem ele fura o invariante.
        config(['maintenance.auto_verify_linked_workshop' => true]);

        $vehicle = $this->vehicleOwnedByWorkshopTenant();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $this->workshop->id,
            'user_id' => $this->workshopUser->id,
            'verified_at' => null,
        ]);

        $stamped = $this->stamper->stamp($maintenance, $this->workshopUser);

        $this->assertNull($stamped->verified_at);
    }

    // ── sealAfterOwnerLink: selo retroativo ──────────────────────────────────

    public function test_seal_after_owner_link_does_not_seal_a_vehicle_of_the_workshop_tenant(): void
    {
        $vehicle = $this->vehicleOwnedByWorkshopTenant();
        $maintenance = $this->workshopMaintenance($vehicle);

        $sealed = $this->stamper->sealAfterOwnerLink($maintenance, (int) $this->workshop->id);

        $this->assertNull($sealed->verified_at);
    }

    public function test_seal_after_owner_link_still_seals_for_a_third_party_owner(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);
        $maintenance = $this->workshopMaintenance($vehicle);

        $sealed = $this->stamper->sealAfterOwnerLink($maintenance, (int) $this->workshop->id);

        $this->assertNotNull($sealed->verified_at);
        $this->assertSame('confirmed', $sealed->verification_method);
    }

    // ── confirmDeclared e a fila de validação ────────────────────────────────

    public function test_confirm_declared_refuses_a_vehicle_of_the_workshop_tenant(): void
    {
        $vehicle = $this->vehicleOwnedByWorkshopTenant();
        $maintenance = $this->declaredMaintenance($vehicle);

        // Chega direto no gargalo: o caminho de tela é barrado antes, pela policy (teste abaixo).
        $this->expectException(SelfAttestationException::class);

        $this->stamper->confirmDeclared($maintenance, (int) $this->workshop->id);
    }

    public function test_confirm_declared_still_seals_a_declared_record_on_a_third_party_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);
        $maintenance = $this->declaredMaintenance($vehicle);

        $sealed = $this->stamper->confirmDeclared($maintenance, (int) $this->workshop->id);

        $this->assertNotNull($sealed->verified_at);
        $this->assertSame('confirmed', $sealed->verification_method);
    }

    public function test_confirming_is_denied_when_the_vehicle_belongs_to_the_workshop_tenant(): void
    {
        $maintenance = $this->pendingReview($this->vehicleOwnedByWorkshopTenant());

        $this->assertFalse(Gate::forUser($this->workshopUser)->allows('confirmReview', $maintenance));
    }

    public function test_confirming_is_allowed_for_a_declared_record_on_a_third_party_vehicle(): void
    {
        $maintenance = $this->pendingReview($this->thirdPartyVehicle());

        $this->assertTrue(Gate::forUser($this->workshopUser)->allows('confirmReview', $maintenance));
    }

    /**
     * A posse pode mudar **depois** do pedido, e aí o pendente já está na fila: `scopeAwaitingReviewBy`
     * lista por status e workshop_id, sem olhar posse, e nada regrava a declarada para limpá-lo. Se a
     * guarda estivesse em `review` as duas respostas morreriam e o item ficaria preso para sempre —
     * não reconhecer não emite nada, então continua sendo a saída.
     */
    public function test_rejecting_stays_possible_when_the_vehicle_became_the_workshop_tenants(): void
    {
        $maintenance = $this->pendingReview($this->vehicleOwnedByWorkshopTenant());

        $this->assertTrue(Gate::forUser($this->workshopUser)->allows('review', $maintenance));
    }

    public function test_the_queue_screen_refuses_to_confirm_a_vehicle_of_the_workshop_tenant(): void
    {
        $maintenance = $this->pendingReview($this->vehicleOwnedByWorkshopTenant());

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.confirm', $maintenance))
            ->assertRedirect(route('workshop.reviews.index'))
            ->assertSessionHas('error', ReviewController::ownVehicleMessage());

        $this->assertNull($maintenance->fresh()->verified_at);
        $this->assertSame(WorkshopReviewStatus::Pending, $maintenance->fresh()->workshop_review_status);
    }

    public function test_the_queue_screen_hides_the_confirm_button_for_a_vehicle_of_the_workshop_tenant(): void
    {
        $this->pendingReview($this->vehicleOwnedByWorkshopTenant());

        $this->actingAs($this->workshopUser)
            ->get(route('workshop.reviews.index'))
            ->assertOk()
            ->assertDontSee('Confirmar serviço')
            ->assertSee('Não reconheço');
    }

    /**
     * A saída da fila funciona de ponta a ponta: o item preso sai pela rejeição.
     */
    public function test_the_queue_screen_still_rejects_a_vehicle_of_the_workshop_tenant(): void
    {
        Notification::fake();

        $maintenance = $this->pendingReview($this->vehicleOwnedByWorkshopTenant());

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.reject', $maintenance))
            ->assertRedirect(route('workshop.reviews.index'))
            ->assertSessionHasNoErrors();

        $fresh = $maintenance->fresh();
        $this->assertSame(WorkshopReviewStatus::Rejected, $fresh->workshop_review_status);
        $this->assertNull($fresh->verified_at);
    }

    public function test_the_queue_screen_still_confirms_a_third_party_vehicle(): void
    {
        Notification::fake();

        $maintenance = $this->pendingReview($this->thirdPartyVehicle());

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.confirm', $maintenance))
            ->assertRedirect(route('workshop.reviews.index'));

        $this->assertNotNull($maintenance->fresh()->verified_at);
    }

    public function test_a_declaration_the_workshop_could_never_confirm_is_not_queued_for_review(): void
    {
        Notification::fake();

        $maintenance = $this->declaredMaintenance($this->vehicleOwnedByWorkshopTenant());

        app(WorkshopReviewService::class)->syncAfterDeclaration($maintenance);

        $this->assertNull($maintenance->fresh()->workshop_review_status);
        Notification::assertNothingSent();
    }

    public function test_a_declaration_on_a_third_party_vehicle_is_still_queued_for_review(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);
        $maintenance = $this->declaredMaintenance($vehicle);

        app(WorkshopReviewService::class)->syncAfterDeclaration($maintenance);

        $this->assertSame(WorkshopReviewStatus::Pending, $maintenance->fresh()->workshop_review_status);
    }

    // ── backfill ─────────────────────────────────────────────────────────────

    public function test_the_backfill_skips_what_the_workshop_cannot_attest(): void
    {
        $own = $this->workshopMaintenance($this->vehicleOwnedByWorkshopTenant());
        $thirdParty = $this->workshopMaintenance(Vehicle::factory()->create());

        $count = $this->stamper->verifyLinkedWorkshopMaintenances();

        $this->assertSame(1, $count);
        $this->assertNull($own->fresh()->verified_at);
        $this->assertNotNull($thirdParty->fresh()->verified_at);
    }

    // ── oficina sem tenant: a guarda não pode desligar em silêncio ───────────

    /**
     * `workshops.tenant_id` é nullable e `nullOnDelete`. Comparar só tenant faria nulo casar com
     * nulo em lugar nenhum, e a conta de cadastro torto selaria o próprio carro — o inverso de
     * falhar fechado. O lado da oficina são os dois identificadores.
     */
    public function test_a_workshop_without_tenant_does_not_seal_its_own_users_vehicle(): void
    {
        $vehicle = $this->vehicleOwnedByWorkshopTenant();
        $this->forgetWorkshopTenant($vehicle);

        $stamped = $this->stamper->stamp($this->workshopMaintenance($vehicle), $this->workshopUser->fresh());

        $this->assertNull($stamped->verified_at);
        $this->assertNull($stamped->verification_code);
    }

    public function test_a_workshop_without_tenant_still_seals_a_third_party_vehicle(): void
    {
        $vehicle = $this->thirdPartyVehicle();
        $this->forgetWorkshopTenant();

        $stamped = $this->stamper->stamp($this->workshopMaintenance($vehicle), $this->workshopUser->fresh());

        $this->assertNotNull($stamped->verified_at);
        $this->assertNotNull($stamped->verification_code);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Apaga todo rastro de tenant do lado da oficina — e, quando o veículo é dela, também da posse,
     * que é como os dados ficam numa conta que nasceu sem tenant.
     */
    private function forgetWorkshopTenant(?Vehicle $ownVehicle = null): void
    {
        $this->workshop->forceFill(['tenant_id' => null])->save();
        $this->workshopUser->forceFill(['tenant_id' => null])->save();

        if ($ownVehicle !== null) {
            $this->workshopUser->vehicles()->updateExistingPivot($ownVehicle->id, ['tenant_id' => null]);
        }
    }

    private function thirdPartyVehicle(): Vehicle
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        return $vehicle->fresh();
    }

    /**
     * Declarada já na fila de validação da oficina.
     */
    private function pendingReview(Vehicle $vehicle): Maintenance
    {
        $maintenance = $this->declaredMaintenance($vehicle);
        $maintenance->forceFill(['workshop_review_status' => WorkshopReviewStatus::Pending])->save();

        return $maintenance->fresh();
    }

    private function vehicleOwnedByWorkshopTenant(): Vehicle
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($this->workshopUser, $vehicle);
        $this->workshopUser->vehicles()->updateExistingPivot($vehicle->id, [
            'ownership_verified_at' => now(),
        ]);

        return $vehicle->fresh();
    }

    /**
     * OS que a própria oficina registrou, ainda sem selo.
     */
    private function workshopMaintenance(Vehicle $vehicle): Maintenance
    {
        return Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $this->workshop->id,
            'user_id' => $this->workshopUser->id,
            'verified_at' => null,
            'verified_workshop_id' => null,
            'verification_code' => null,
        ]);
    }

    /**
     * Declarada por um proprietário citando esta oficina.
     */
    private function declaredMaintenance(Vehicle $vehicle): Maintenance
    {
        return Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $this->workshop->id,
            'registered_by_type' => 'owner',
            'verified_at' => null,
            'verified_workshop_id' => null,
            'verification_code' => null,
        ]);
    }
}

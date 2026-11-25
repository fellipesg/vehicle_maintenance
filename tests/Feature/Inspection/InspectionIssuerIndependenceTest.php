<?php

namespace Tests\Feature\Inspection;

use App\Enums\InspectionVerdict;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Policies\VehicleInspectionPolicy;
use App\Services\Inspection\VehicleInspectionService;
use App\Support\Inspection\InspectionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * O mesmo invariante do selo, no laudo de vistoria cautelar: a oficina não atesta a condição de um
 * carro do tenant dela.
 *
 * A doutrina do conflito de interesse já estava escrita em `App\Support\Inspection\InspectionAccess`
 * — mas por **tipo de papel** (lojista não emite laudo). Aqui ela passa a valer por **dono do
 * veículo**, que é a metade que faltava, e com o par de sempre: conflitado negado, terceiro intacto.
 */
class InspectionIssuerIndependenceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleInspectionService $service;

    private User $workshopUser;

    private Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableVehicleInspectionsForEveryone();
        $this->service = app(VehicleInspectionService::class);
        $this->workshopUser = User::factory()->asWorkshop()->create()->fresh();
        $this->workshop = $this->workshopUser->workshop;
    }

    // ── o serviço, que é a única escrita de veredito e achados ───────────────

    public function test_the_service_refuses_an_inspection_on_a_vehicle_of_the_workshop_tenant(): void
    {
        $vehicle = $this->vehicleOwnedByWorkshopTenant();

        try {
            $this->service->create($vehicle, $this->workshop, $this->workshopUser, $this->payload());
            $this->fail('O laudo sobre o próprio carro deveria ter sido recusado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('verdict', $e->errors());
            $this->assertStringContainsString('própria conta', $e->errors()['verdict'][0]);
        }

        $this->assertDatabaseCount('vehicle_inspections', 0);
    }

    public function test_the_service_still_issues_an_inspection_on_a_third_party_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        $inspection = $this->service->create($vehicle, $this->workshop, $this->workshopUser, $this->payload());

        $this->assertSame(InspectionVerdict::ApprovedWithRestrictions, $inspection->verdict);
        $this->assertStringStartsWith('LDO-', (string) $inspection->verification_code);
    }

    public function test_the_service_still_issues_an_inspection_on_a_vehicle_with_no_owner(): void
    {
        $inspection = $this->service->create(
            Vehicle::factory()->create(),
            $this->workshop,
            $this->workshopUser,
            $this->payload(),
        );

        $this->assertNotNull($inspection->verification_code);
    }

    public function test_unverified_ownership_of_the_workshop_tenant_still_refuses_the_inspection(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($this->workshopUser, $vehicle);

        $this->expectException(ValidationException::class);

        $this->service->create($vehicle, $this->workshop, $this->workshopUser, $this->payload());
    }

    public function test_an_active_consignment_of_the_workshop_tenant_refuses_the_inspection(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleConsignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'tenant_id' => $this->workshopUser->tenant_id,
            'status' => VehicleConsignment::STATUS_ACTIVE,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->create($vehicle, $this->workshop, $this->workshopUser, $this->payload());
    }

    public function test_the_verdict_cannot_be_rewritten_after_the_workshop_tenant_buys_the_car(): void
    {
        // O laudo nasceu legítimo, sobre carro de terceiro.
        $vehicle = Vehicle::factory()->create();
        $owner = User::factory()->asUser()->create();
        $this->attachVehicleToUser($owner, $vehicle);
        $inspection = $this->service->create($vehicle, $this->workshop, $this->workshopUser, $this->payload([
            'verdict' => InspectionVerdict::Rejected->value,
        ]));

        // Depois o carro passa para a conta da oficina: reescrever o veredito é a fraude.
        $owner->vehicles()->updateExistingPivot($vehicle->id, ['is_current_owner' => false]);
        $this->attachVehicleToUser($this->workshopUser, $vehicle);

        $this->expectException(ValidationException::class);

        $this->service->update($inspection->fresh(), [
            'verdict' => InspectionVerdict::Approved->value,
        ]);
    }

    public function test_a_change_that_does_not_touch_the_claim_is_not_re_judged(): void
    {
        // As guardas rodam por campo que mudou: corrigir só a data não reabre a pergunta do veredito
        // (.ai/rules/inspections.md). Quem barra a conta conflitada nessa edição é a policy.
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);
        $inspection = $this->service->create($vehicle, $this->workshop, $this->workshopUser, $this->payload());

        $this->attachVehicleToUser($this->workshopUser, Vehicle::factory()->create());
        $this->attachVehicleToUser($this->workshopUser, $vehicle);

        $updated = $this->service->update($inspection->fresh(), [
            'inspected_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame(now()->subDay()->toDateString(), $updated->inspected_at->toDateString());
    }

    // ── a policy ─────────────────────────────────────────────────────────────

    public function test_the_policy_denies_creating_about_a_vehicle_of_the_workshop_tenant(): void
    {
        $response = Gate::forUser($this->workshopUser)
            ->inspect('create', [VehicleInspection::class, $this->vehicleOwnedByWorkshopTenant()]);

        $this->assertFalse($response->allowed());
        $this->assertSame(VehicleInspectionPolicy::DENIED_OWN_VEHICLE, $response->code());
    }

    public function test_the_policy_allows_creating_about_a_third_party_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        $this->assertTrue(
            Gate::forUser($this->workshopUser)->allows('create', [VehicleInspection::class, $vehicle]),
        );
    }

    public function test_the_policy_without_a_vehicle_answers_what_it_always_answered(): void
    {
        // Item de menu, botão "Nova vistoria" e a tela antes da etapa 1 perguntam sem veículo.
        $this->attachVehicleToUser($this->workshopUser, Vehicle::factory()->create());

        $this->assertTrue(Gate::forUser($this->workshopUser)->allows('create', VehicleInspection::class));
    }

    public function test_the_issuer_cannot_edit_or_delete_a_report_about_a_car_it_now_owns(): void
    {
        $vehicle = $this->vehicleOwnedByWorkshopTenant();
        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $this->workshop->id,
        ]);

        foreach (['update', 'delete'] as $ability) {
            $response = Gate::forUser($this->workshopUser)->inspect($ability, $inspection);

            $this->assertFalse($response->allowed(), "A oficina não deveria poder {$ability}.");
            $this->assertSame(VehicleInspectionPolicy::DENIED_OWN_VEHICLE, $response->code());
        }
    }

    public function test_the_issuer_still_edits_and_deletes_a_report_about_a_third_party_car(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);
        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->assertTrue(Gate::forUser($this->workshopUser)->allows('update', $inspection));
        $this->assertTrue(Gate::forUser($this->workshopUser)->allows('delete', $inspection));
    }

    // ── InspectionAccess, a única resposta de "pode emitir?" ─────────────────

    public function test_allows_issuing_about_separates_the_flag_from_the_conflict(): void
    {
        $own = $this->vehicleOwnedByWorkshopTenant();
        $thirdParty = Vehicle::factory()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $thirdParty);

        $this->assertTrue(InspectionAccess::allowsIssuing($this->workshopUser));
        $this->assertFalse(InspectionAccess::allowsIssuingAbout($this->workshopUser, $own));
        $this->assertTrue(InspectionAccess::allowsIssuingAbout($this->workshopUser, $thirdParty));
        $this->assertTrue(InspectionAccess::allowsIssuingAbout($this->workshopUser, null));
    }

    // ── a tela da oficina ────────────────────────────────────────────────────

    public function test_the_workshop_screen_refuses_the_plate_of_its_own_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        $this->attachVehicleToUser($this->workshopUser, $vehicle);

        $this->actingAs($this->workshopUser->fresh())
            ->post('/oficina/vistorias', $this->payload(['license_plate' => 'ABC1D23']))
            ->assertSessionHasErrors('license_plate');

        $this->assertDatabaseCount('vehicle_inspections', 0);
    }

    public function test_the_workshop_screen_still_issues_for_the_plate_of_a_third_party_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        $this->actingAs($this->workshopUser->fresh())
            ->post('/oficina/vistorias', $this->payload(['license_plate' => 'ABC1D23']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $vehicle->inspections()->count());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'inspected_at' => now()->toDateString(),
            'verdict' => InspectionVerdict::ApprovedWithRestrictions->value,
        ], $overrides);
    }
}

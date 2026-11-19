<?php

namespace Tests\Feature\Inspection;

use App\Enums\InspectionFinding;
use App\Enums\InspectionFindingStatus;
use App\Enums\InspectionVerdict;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Support\Inspection\InspectionRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quem vê um laudo e quanto dele vê. A listagem (Vehicle::inspectionsVisibleTo) e a forma mínima
 * (InspectionRedactor) precisam concordar com a policy — é a mesma exigência que
 * .ai/rules/policies.md faz para manutenções.
 */
class VehicleInspectionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_current_owner_lists_the_report_and_another_tenant_does_not(): void
    {
        $owner = User::factory()->asUser()->create();
        $stranger = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $inspection = VehicleInspection::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->assertTrue(
            $vehicle->inspectionsVisibleTo($owner->fresh())->pluck('id')->contains($inspection->id),
        );
        $this->assertSame(0, $vehicle->inspectionsVisibleTo($stranger->fresh())->count());
    }

    public function test_a_report_the_staff_suppressed_leaves_the_owner_list_and_stays_with_the_issuer(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshopUser = User::factory()->asWorkshop()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $inspection = VehicleInspection::factory()->hiddenByStaff()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshopUser->fresh()->workshop->id,
        ]);

        $this->assertSame(0, $vehicle->inspectionsVisibleTo($owner->fresh())->count());
        $this->assertSame(0, VehicleInspection::query()->publiclyVisible()->count());

        // Para o emissor o laudo continua: ele o alcança pelas telas da própria oficina, não pela
        // listagem do veículo — que, como em Vehicle::maintenancesVisibleTo, fecha por tenant para
        // quem não passa em viewFullHistory.
        $this->assertTrue($inspection->fresh()->hiddenFrom($owner->fresh()));
        $this->assertFalse($inspection->fresh()->hiddenFrom($workshopUser->fresh()));
    }

    public function test_the_in_memory_answer_matches_the_query(): void
    {
        $owner = User::factory()->asUser()->create();
        $stranger = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $inspection = VehicleInspection::factory()->create(['vehicle_id' => $vehicle->id]);

        foreach ([$owner, $stranger] as $viewer) {
            $viewer = $viewer->fresh();
            $listed = $vehicle->inspectionsVisibleTo($viewer)->pluck('id')->contains($inspection->id);

            $this->assertSame(
                $listed,
                $inspection->fresh()->isVisibleInHistoryTo($viewer),
                'listagem e resposta em memória divergiram para '.$viewer->email,
            );
        }
    }

    public function test_a_visitor_loses_the_prose_and_the_pdf_but_keeps_every_axis_status(): void
    {
        $vehicle = Vehicle::factory()->create();
        $inspection = VehicleInspection::factory()
            ->verdict(InspectionVerdict::Rejected)
            ->failing(InspectionFinding::Identification, 'Chassi remarcado no compartimento do motor.')
            ->finding(InspectionFinding::Paint, InspectionFindingStatus::Attention, 'Repintura na lateral.')
            ->create([
                'vehicle_id' => $vehicle->id,
                'summary' => 'Veículo reprovado. Proprietário informou sinistro em 2024.',
                'odometer_km' => 70_000,
            ]);

        $inspection->forceFill([
            'report_path' => 'inspections/abc.pdf',
            'report_original_name' => 'laudo.pdf',
        ])->save();

        $minimal = InspectionRedactor::redact($inspection->fresh(), null);

        $this->assertNull($minimal->summary);
        $this->assertNull($minimal->report_path);
        $this->assertNull($minimal->report_original_name);

        // O que o comprador precisa continua: veredito, hodômetro e o status de cada eixo.
        $this->assertSame(InspectionVerdict::Rejected, $minimal->verdict);
        $this->assertSame(70_000, $minimal->odometer_km);
        $this->assertSame(InspectionFinding::values(), array_keys($minimal->findings));
        $this->assertSame('fail', $minimal->findings['identification']['status']);
        $this->assertSame('attention', $minimal->findings['paint']['status']);

        // E a prosa de cada eixo, não.
        foreach ($minimal->findings as $axis => $entry) {
            $this->assertNull($entry['note'], $axis.' manteve a observação');
        }

        // A cópia é em memória: o laudo gravado não perdeu nada.
        $this->assertNotNull($inspection->fresh()->summary);
        $this->assertSame(
            'Chassi remarcado no compartimento do motor.',
            $inspection->fresh()->findings['identification']['note'],
        );
    }

    public function test_the_owner_and_the_issuer_see_the_whole_report(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshopUser = User::factory()->asWorkshop()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $inspection = VehicleInspection::factory()
            ->failing(InspectionFinding::Structural, 'Longarina com solda fora do padrão.')
            ->verdict(InspectionVerdict::Rejected)
            ->create([
                'vehicle_id' => $vehicle->id,
                'workshop_id' => $workshopUser->fresh()->workshop->id,
                'summary' => 'Reprovado por estrutura.',
            ]);

        foreach ([$owner, $workshopUser] as $viewer) {
            $full = InspectionRedactor::redact($inspection->fresh(), $viewer->fresh());

            $this->assertSame('Reprovado por estrutura.', $full->summary, $viewer->user_type);
            $this->assertSame(
                'Longarina com solda fora do padrão.',
                $full->findings['structural']['note'],
                $viewer->user_type,
            );
        }
    }

    public function test_an_expired_report_stays_in_the_history_marked_as_expired(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $expired = VehicleInspection::factory()->expired()->create(['vehicle_id' => $vehicle->id]);
        $current = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'inspected_at' => now()->subDays(10)->format('Y-m-d'),
            'valid_until' => now()->addDays(80)->format('Y-m-d'),
        ]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($current->isExpired());
        $this->assertSame(2, $vehicle->inspectionsVisibleTo($owner->fresh())->count());
    }

    public function test_a_report_with_no_validity_never_expires(): void
    {
        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => Vehicle::factory(),
            'valid_until' => null,
        ]);

        $this->assertFalse($inspection->isExpired());
    }

    public function test_the_provenance_label_never_claims_the_workshop_seal(): void
    {
        $inspection = VehicleInspection::factory()
            ->verdict(InspectionVerdict::ApprovedWithRestrictions)
            ->create(['vehicle_id' => Vehicle::factory(), 'workshop_name' => 'Vistorias Alfa']);

        $this->assertSame('Vistoria cautelar — Aprovado com restrições', $inspection->provenance_label);
        $this->assertSame('Vistorias Alfa', $inspection->provenance_sublabel);
        $this->assertStringNotContainsStringIgnoringCase('selo', $inspection->provenance_label);
    }
}

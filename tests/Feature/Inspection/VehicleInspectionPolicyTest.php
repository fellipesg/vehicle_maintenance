<?php

namespace Tests\Feature\Inspection;

use App\Enums\WorkshopStatus;
use App\Features\VehicleInspections;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Policies\VehicleInspectionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;
use Tests\TestCase;

/**
 * Emitir laudo é mais restrito que registrar OS: aprovação da oficina **mais** a funcionalidade
 * ligada por um admin. Aprovação diz "esta empresa é quem diz ser"; não diz que ela atesta a
 * condição do carro de um terceiro.
 */
class VehicleInspectionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_workshop_without_the_feature_cannot_issue(): void
    {
        [$user] = $this->workshop();

        $response = Gate::forUser($user)->inspect('create', VehicleInspection::class);

        $this->assertFalse($response->allowed());
        $this->assertSame(VehicleInspectionPolicy::DENIED_FEATURE_OFF, $response->code());
    }

    public function test_the_feature_on_the_workshop_scope_allows_issuing(): void
    {
        [$user, $workshop] = $this->workshop();

        Feature::for($workshop)->activate(VehicleInspections::class);

        $this->assertTrue(Gate::forUser($user->fresh())->allows('create', VehicleInspection::class));
    }

    public function test_the_feature_on_the_account_scope_allows_issuing(): void
    {
        [$user] = $this->workshop();

        Feature::for($user)->activate(VehicleInspections::class);

        $this->assertTrue(Gate::forUser($user->fresh())->allows('create', VehicleInspection::class));
    }

    public function test_a_workshop_still_under_review_cannot_issue_even_with_the_feature_on(): void
    {
        [$user, $workshop] = $this->workshop();
        $workshop->forceFill(['status' => WorkshopStatus::Pending])->save();

        Feature::for($workshop)->activate(VehicleInspections::class);
        Feature::for($user)->activate(VehicleInspections::class);

        $response = Gate::forUser($user->fresh())->inspect('create', VehicleInspection::class);

        $this->assertFalse($response->allowed());
        // Recusa por análise, não por funcionalidade: a tela não deve dizer "pede para o admin ligar".
        $this->assertNotSame(VehicleInspectionPolicy::DENIED_FEATURE_OFF, $response->code());
    }

    public function test_an_owner_and_a_dealer_never_issue_a_report(): void
    {
        foreach ([User::factory()->asUser(), User::factory()->asGarage()] as $factory) {
            $user = $factory->create();
            Feature::for($user)->activate(VehicleInspections::class);

            $this->assertFalse(
                Gate::forUser($user->fresh())->allows('create', VehicleInspection::class),
                $user->user_type.' não deveria emitir laudo',
            );
        }
    }

    public function test_only_the_issuing_workshop_corrects_or_deletes_the_report(): void
    {
        [$issuerUser, $issuer] = $this->workshop();
        [$otherUser, $other] = $this->workshop();

        Feature::for($issuer)->activate(VehicleInspections::class);
        Feature::for($other)->activate(VehicleInspections::class);

        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => Vehicle::factory(),
            'workshop_id' => $issuer->id,
        ]);

        $this->assertTrue(Gate::forUser($issuerUser->fresh())->allows('update', $inspection));
        $this->assertTrue(Gate::forUser($issuerUser->fresh())->allows('delete', $inspection));
        $this->assertFalse(Gate::forUser($otherUser->fresh())->allows('update', $inspection));
        $this->assertFalse(Gate::forUser($otherUser->fresh())->allows('delete', $inspection));
    }

    public function test_the_issuer_cannot_correct_the_report_after_the_feature_is_turned_off(): void
    {
        [$user, $workshop] = $this->workshop();

        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => Vehicle::factory(),
            'workshop_id' => $workshop->id,
        ]);

        $response = Gate::forUser($user)->inspect('update', $inspection);

        $this->assertFalse($response->allowed());
        $this->assertSame(VehicleInspectionPolicy::DENIED_FEATURE_OFF, $response->code());
    }

    public function test_the_issuer_still_sees_a_report_the_staff_suppressed_and_another_workshop_does_not(): void
    {
        [$issuerUser, $issuer] = $this->workshop();
        [$otherUser] = $this->workshop();

        $inspection = VehicleInspection::factory()->hiddenByStaff()->create([
            'vehicle_id' => Vehicle::factory(),
            'workshop_id' => $issuer->id,
        ]);

        $this->assertTrue(Gate::forUser($issuerUser->fresh())->allows('view', $inspection));
        $this->assertFalse(Gate::forUser($otherUser->fresh())->allows('view', $inspection));
    }

    /**
     * UserFactory::configure() já cria a oficina (aprovada) da conta de oficina: criar outra deixaria
     * duas linhas com o mesmo user_id, e `$user->workshop` devolveria a primeira — não a do teste.
     *
     * @return array{0: User, 1: Workshop}
     */
    private function workshop(): array
    {
        $user = User::factory()->asWorkshop()->create();

        return [$user->fresh(), $user->fresh()->workshop];
    }
}

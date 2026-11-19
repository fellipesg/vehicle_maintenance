<?php

namespace Tests\Feature\Inspection;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A supressão de um laudo pela equipe, e a tela em que ela acontece.
 *
 * A divisão é o ponto: o proprietário contesta (marca, não esconde) e a equipe suprime (tira de
 * circulação), com nome e motivo gravados. Suprimir apaga da vista do comprador justamente o que ele
 * precisa ver, e sem sinal — "carro sem laudo" fica indistinguível de "laudo suprimido" —, então a
 * alavanca não fica na mão de quem tem interesse em esconder.
 *
 * A tela do admin é a única que mostra o laudo suprimido: quem tirou precisa continuar vendo para
 * poder devolver (.ai/rules/admin.md).
 */
class AdminInspectionSuppressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_staff_suppresses_a_report_with_a_name_and_a_reason(): void
    {
        $admin = $this->admin();
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        $this->actingAs($admin)
            ->post(route('admin.inspections.hide', $inspection), ['hidden_reason' => 'Laudo emitido para outro chassi.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $inspection->refresh();

        $this->assertNotNull($inspection->hidden_from_public_at);
        $this->assertSame($admin->id, $inspection->hidden_by_user_id);
        $this->assertSame('Laudo emitido para outro chassi.', $inspection->hidden_reason);

        // E some das superfícies: a ficha do dono e a conferência pública.
        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertDontSee('data-inspection-card', false);

        $this->get(route('inspections.public', $inspection->verification_code))->assertNotFound();
    }

    public function test_the_issuing_workshop_still_sees_the_report_it_signed(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create()->fresh();
        $workshop = $workshopUser->workshop;
        [, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle, ['workshop_id' => $workshop->id]);

        $this->actingAs($this->admin())->post(route('admin.inspections.hide', $inspection), []);

        $this->actingAs($workshopUser->fresh())
            ->get(route('workshop.inspections.show', $inspection))
            ->assertOk();
    }

    public function test_the_admin_list_keeps_showing_the_suppressed_report_and_offers_it_back(): void
    {
        $admin = $this->admin();
        [, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        $this->actingAs($admin)->post(route('admin.inspections.hide', $inspection), ['hidden_reason' => 'Fraude confirmada.']);

        $this->actingAs($admin)
            ->get(route('admin.inspections.index'))
            ->assertOk()
            ->assertSee($inspection->verification_code)
            ->assertSee('Suprimido')
            ->assertSee('Fraude confirmada.')
            ->assertSee($admin->name)
            ->assertSee(route('admin.inspections.restore', $inspection), false);
    }

    public function test_the_staff_puts_it_back(): void
    {
        $admin = $this->admin();
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = VehicleInspection::factory()->hiddenByStaff()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.inspections.restore', $inspection))
            ->assertRedirect()
            ->assertSessionHas('success');

        $inspection->refresh();

        $this->assertNull($inspection->hidden_from_public_at);
        $this->assertNull($inspection->hidden_by_user_id);
        $this->assertNull($inspection->hidden_reason);

        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-inspection-card', false);
    }

    /**
     * As duas colunas são independentes de propósito: devolver um laudo ao histórico não pode apagar
     * em silêncio que o dono discordou dele.
     */
    public function test_suppressing_and_restoring_never_clears_the_owners_dispute(): void
    {
        $admin = $this->admin();
        [, $vehicle] = $this->ownerWithVehicle();
        $inspection = VehicleInspection::factory()->disputed()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);

        $this->actingAs($admin)->post(route('admin.inspections.hide', $inspection), []);
        $this->assertNotNull($inspection->fresh()->owner_disputed_at);

        $this->actingAs($admin)->post(route('admin.inspections.restore', $inspection));

        $inspection->refresh();
        $this->assertNotNull($inspection->owner_disputed_at);
        $this->assertSame('Não reconheço esta vistoria.', $inspection->owner_dispute_reason);
    }

    public function test_the_list_filters_what_was_disputed_and_what_was_suppressed(): void
    {
        $admin = $this->admin();
        [, $vehicle] = $this->ownerWithVehicle();

        $plain = $this->inspection($vehicle);
        $disputed = VehicleInspection::factory()->disputed()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);
        $hidden = VehicleInspection::factory()->hiddenByStaff()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.inspections.index', ['contestadas' => '1']))
            ->assertOk()
            ->assertSee($disputed->verification_code)
            ->assertDontSee($plain->verification_code)
            ->assertDontSee($hidden->verification_code);

        $this->actingAs($admin)
            ->get(route('admin.inspections.index', ['suprimidas' => '1']))
            ->assertOk()
            ->assertSee($hidden->verification_code)
            ->assertDontSee($plain->verification_code);

        $this->actingAs($admin)
            ->get(route('admin.inspections.index', ['q' => 'ABC1D23']))
            ->assertOk()
            ->assertSee($plain->verification_code);
    }

    public function test_the_screen_and_the_action_are_only_for_the_staff(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        // O middleware admin devolve a conta comum para a área dela, sem chegar ao serviço.
        $this->actingAs($owner)->get(route('admin.inspections.index'))->assertRedirect();
        $this->actingAs($owner)->post(route('admin.inspections.hide', $inspection), [])->assertRedirect();
        $this->actingAs($owner)->post(route('admin.inspections.restore', $inspection))->assertRedirect();

        $this->assertNull($inspection->fresh()->hidden_from_public_at);
    }

    /**
     * O caminho até a tela: o cartão de Pendências da visão geral, como nas OS contestadas.
     */
    public function test_the_overview_points_the_staff_at_the_disputed_reports(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();
        VehicleInspection::factory()->disputed()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('laudo contestado')
            ->assertSee(route('admin.inspections.index', ['contestadas' => '1']), false);
    }

    private function admin(): User
    {
        return User::factory()->asUser()->asAdmin()->create(['name' => 'Equipe RevisaLog'])->fresh();
    }

    /**
     * @return array{0: User, 1: Vehicle}
     */
    private function ownerWithVehicle(): array
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner->fresh(), $vehicle->fresh()];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function inspection(Vehicle $vehicle, array $attributes = []): VehicleInspection
    {
        return VehicleInspection::factory()->create(array_merge([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create(['name' => 'Vistoria Confiável'])->id,
            'workshop_name' => 'Vistoria Confiável',
            'summary' => null,
        ], $attributes));
    }
}

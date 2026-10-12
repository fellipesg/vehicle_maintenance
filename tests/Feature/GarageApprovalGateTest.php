<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Enums\Portal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enquanto a loja não é aprovada, a conta de garagem só abre "Minha loja". O portão existe porque
 * uma garagem escreve no histórico permanente de um chassi e dispara e-mail para terceiros.
 */
class GarageApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    private function dealer(GarageStatus $status, ?string $notes = null): User
    {
        $dealer = User::factory()->asGarage()->create();

        $dealer->garage->forceFill([
            'status' => $status,
            'review_notes' => $notes,
        ])->save();

        return $dealer->fresh();
    }

    /**
     * @return list<string>
     */
    private function blockedRoutes(): array
    {
        return ['garage.dashboard', 'garage.vehicles.index', 'garage.vehicles.create', 'garage.maintenances.index'];
    }

    public function test_pending_store_is_sent_back_to_minha_loja_from_every_other_screen(): void
    {
        $dealer = $this->dealer(GarageStatus::Pending);

        foreach ($this->blockedRoutes() as $route) {
            $this->actingAs($dealer)
                ->get(route($route))
                ->assertRedirect(route('garage.profile.show'));
        }
    }

    public function test_rejected_store_is_blocked_the_same_way(): void
    {
        $dealer = $this->dealer(GarageStatus::Rejected);

        $this->actingAs($dealer)
            ->get(route('garage.vehicles.index'))
            ->assertRedirect(route('garage.profile.show'));
    }

    public function test_pending_store_still_opens_and_saves_minha_loja(): void
    {
        $dealer = $this->dealer(GarageStatus::Pending);

        $this->actingAs($dealer)
            ->get(route('garage.profile.show'))
            ->assertOk()
            ->assertSee('data-garage-pending', escape: false);

        $this->actingAs($dealer)
            ->put(route('garage.profile.update'), ['name' => 'Loja em análise', 'document' => '12345678000190'])
            ->assertRedirect(route('garage.profile.show'))
            ->assertSessionHasNoErrors();

        $this->assertSame('12345678000190', $dealer->fresh()->garage->document);
    }

    public function test_approved_store_passes_through(): void
    {
        $dealer = $this->dealer(GarageStatus::Approved);

        foreach ($this->blockedRoutes() as $route) {
            $this->actingAs($dealer)->get(route($route))->assertOk();
        }
    }

    public function test_the_form_never_changes_the_status(): void
    {
        $dealer = $this->dealer(GarageStatus::Pending);

        $this->actingAs($dealer)->put(route('garage.profile.update'), [
            'name' => 'Loja do Dev',
            'status' => GarageStatus::Approved->value,
            'reviewed_at' => now()->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(GarageStatus::Pending, $dealer->fresh()->garage->status);
        $this->assertNull($dealer->fresh()->garage->reviewed_at);
    }

    public function test_rejected_store_sees_the_reason(): void
    {
        $dealer = $this->dealer(GarageStatus::Rejected, 'CNPJ baixado na Receita.');

        $this->actingAs($dealer)
            ->get(route('garage.profile.show'))
            ->assertOk()
            ->assertSee('CNPJ baixado na Receita.');
    }

    public function test_pending_store_lands_on_minha_loja_and_sees_only_it_in_the_menu(): void
    {
        $dealer = $this->dealer(GarageStatus::Pending);

        // Sem isto o login mandaria para o dashboard, que o portão devolve: laço de redirecionamento.
        $this->assertSame('garage.profile.show', Portal::Dealer->dashboardRouteFor($dealer));
        $this->assertNull(Portal::Dealer->primaryActionFor($dealer));

        $labels = array_column(
            Portal::Dealer->navigationItems(tap(request(), fn ($request) => $request->setUserResolver(fn () => $dealer))),
            'label',
        );

        $this->assertSame(['Minha loja'], $labels);
    }

    public function test_approved_store_keeps_the_whole_menu(): void
    {
        $dealer = $this->dealer(GarageStatus::Approved);

        $this->assertSame('garage.dashboard', Portal::Dealer->dashboardRouteFor($dealer));
        $this->assertNotNull(Portal::Dealer->primaryActionFor($dealer));
    }

    public function test_existing_stores_were_grandfathered_as_approved(): void
    {
        // A migration marca 'approved' o que já existia; a factory segue a mesma regra.
        $this->assertTrue(User::factory()->asGarage()->create()->garage->isApproved());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Models\User;
use App\Notifications\GarageReviewDecidedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fila de análise das lojas: é por aqui que o admin vê o que está esperando e libera (ou recusa)
 * uma garagem. Antes dela a aprovação só existia como UPDATE na linha.
 */
class AdminGarageQueueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->asUser()->asAdmin()->create();
    }

    private function dealer(GarageStatus $status, string $name = 'Loja do Dev'): User
    {
        $dealer = User::factory()->asGarage()->create();
        $dealer->garage->forceFill(['status' => $status, 'name' => $name])->save();

        return $dealer->fresh();
    }

    public function test_the_queue_opens_on_the_stores_awaiting_review(): void
    {
        $this->dealer(GarageStatus::Pending, 'Loja Esperando');
        $this->dealer(GarageStatus::Approved, 'Loja Liberada');

        $this->actingAs($this->admin())
            ->get(route('admin.garages.index'))
            ->assertOk()
            ->assertSee('Loja Esperando')
            ->assertDontSee('Loja Liberada');
    }

    public function test_each_tab_filters_by_status(): void
    {
        $this->dealer(GarageStatus::Pending, 'Loja Esperando');
        $this->dealer(GarageStatus::Approved, 'Loja Liberada');

        $this->actingAs($this->admin())
            ->get(route('admin.garages.index', ['filtro' => GarageStatus::Approved->value]))
            ->assertOk()
            ->assertSee('Loja Liberada')
            ->assertDontSee('Loja Esperando');

        $this->actingAs($this->admin())
            ->get(route('admin.garages.index', ['filtro' => 'todas']))
            ->assertOk()
            ->assertSee('Loja Liberada')
            ->assertSee('Loja Esperando');
    }

    public function test_approving_releases_the_area_and_tells_the_dealer(): void
    {
        Notification::fake();
        $dealer = $this->dealer(GarageStatus::Pending);

        $this->actingAs($this->admin())
            ->post(route('admin.garages.approve', $dealer->garage))
            ->assertRedirect();

        $garage = $dealer->fresh()->garage;

        $this->assertSame(GarageStatus::Approved, $garage->status);
        $this->assertNotNull($garage->reviewed_at);
        $this->assertNotNull($garage->reviewed_by);

        // A área inteira abre na sequência.
        $this->actingAs($dealer->fresh())->get(route('garage.vehicles.index'))->assertOk();

        Notification::assertSentTo($dealer, GarageReviewDecidedNotification::class);
    }

    public function test_rejecting_keeps_the_gate_closed_and_records_the_reason(): void
    {
        Notification::fake();
        $dealer = $this->dealer(GarageStatus::Pending);

        $this->actingAs($this->admin())
            ->post(route('admin.garages.reject', $dealer->garage), ['review_notes' => 'CNPJ baixado na Receita.'])
            ->assertRedirect();

        $garage = $dealer->fresh()->garage;

        $this->assertSame(GarageStatus::Rejected, $garage->status);
        $this->assertSame('CNPJ baixado na Receita.', $garage->review_notes);

        $this->actingAs($dealer->fresh())
            ->get(route('garage.vehicles.index'))
            ->assertRedirect(route('garage.profile.show'));

        Notification::assertSentTo($dealer, GarageReviewDecidedNotification::class);
    }

    public function test_changing_the_cnpj_sends_an_approved_store_back_to_review(): void
    {
        Notification::fake();
        $dealer = $this->dealer(GarageStatus::Approved);
        $dealer->garage->update(['document' => '12345678000190']);
        $dealer->garage->forceFill(['status' => GarageStatus::Approved, 'reviewed_at' => now()])->save();

        $this->actingAs($dealer->fresh())->put(route('garage.profile.update'), [
            'name' => 'Loja do Dev',
            'document' => '98765432000100',
        ])->assertRedirect(route('garage.profile.show'));

        $garage = $dealer->fresh()->garage;

        $this->assertSame(GarageStatus::Pending, $garage->status);
        $this->assertNull($garage->reviewed_at);

        // E o portão volta a valer na hora.
        $this->actingAs($dealer->fresh())
            ->get(route('garage.vehicles.index'))
            ->assertRedirect(route('garage.profile.show'));
    }

    public function test_saving_without_touching_the_cnpj_keeps_the_approval(): void
    {
        $dealer = $this->dealer(GarageStatus::Approved);
        $dealer->garage->update(['document' => '12345678000190']);
        $dealer->garage->forceFill(['status' => GarageStatus::Approved])->save();

        $this->actingAs($dealer->fresh())->put(route('garage.profile.update'), [
            'name' => 'Outro nome',
            'document' => '12.345.678/0001-90',
            'city' => 'São Paulo',
        ])->assertRedirect(route('garage.profile.show'));

        $this->assertSame(GarageStatus::Approved, $dealer->fresh()->garage->status);
    }

    public function test_only_admins_reach_the_queue(): void
    {
        $dealer = $this->dealer(GarageStatus::Pending);

        $this->actingAs($dealer)->get(route('admin.garages.index'))->assertRedirect();
        $this->actingAs(User::factory()->asUser()->create())->get(route('admin.garages.index'))->assertRedirect();
    }
}

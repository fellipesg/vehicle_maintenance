<?php

namespace Tests\Feature;

use App\Enums\Portal;
use App\Enums\WorkshopStatus;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\WorkshopApprovalDecidedNotification;
use App\Services\Workshop\WorkshopReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Enquanto a oficina não é aprovada, a conta de oficina só abre "Minha oficina". O portão é o do
 * lojista (EnsureGarageApproved) pelo mesmo motivo e mais forte: a oficina escreve no histórico
 * permanente de um chassi COM o Selo da oficina, move o hodômetro e manda convite para terceiros.
 */
class WorkshopApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    private function workshopUser(WorkshopStatus $status, ?string $notes = null): User
    {
        $user = User::factory()->asWorkshop()->create();

        $user->workshop->forceFill([
            'status' => $status,
            'review_notes' => $notes,
            'cnpj' => '12345678000190',
        ])->save();

        return $user->fresh();
    }

    /**
     * @return list<string>
     */
    private function blockedRoutes(): array
    {
        return [
            'workshop.dashboard',
            'workshop.maintenances.index',
            'workshop.maintenances.create',
            'workshop.reviews.index',
            'workshop.warranty-templates.index',
        ];
    }

    public function test_pending_workshop_is_sent_back_to_minha_oficina_from_every_other_screen(): void
    {
        $user = $this->workshopUser(WorkshopStatus::Pending);

        foreach ($this->blockedRoutes() as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertRedirect(route('workshop.profile.show'));
        }
    }

    public function test_rejected_workshop_is_blocked_the_same_way(): void
    {
        $this->actingAs($this->workshopUser(WorkshopStatus::Rejected))
            ->get(route('workshop.maintenances.index'))
            ->assertRedirect(route('workshop.profile.show'));
    }

    public function test_pending_workshop_cannot_register_an_order_or_invite_a_customer(): void
    {
        $user = $this->workshopUser(WorkshopStatus::Pending);

        $this->actingAs($user)
            ->post(route('workshop.maintenances.store'), [
                'license_plate' => 'QOS6H54',
                'maintenance_type' => 'Revisão',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 1_000,
                'service_category' => 'mechanical',
            ])
            ->assertRedirect(route('workshop.profile.show'));

        $this->assertSame(0, \App\Models\Maintenance::query()->count());
    }

    public function test_pending_workshop_only_reads_through_the_api(): void
    {
        $user = $this->workshopUser(WorkshopStatus::Pending);
        $this->actingAsApiUser($user);

        $this->getJson('/api/v1/me')->assertOk();

        $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => 1,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 1_000,
            'service_category' => 'mechanical',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Your workshop is still under review. You cannot create or change records yet.');
    }

    public function test_pending_workshop_still_opens_and_saves_minha_oficina(): void
    {
        $user = $this->workshopUser(WorkshopStatus::Pending);

        $this->actingAs($user)
            ->get(route('workshop.profile.show'))
            ->assertOk()
            ->assertSee('data-workshop-pending', escape: false);

        $this->actingAs($user)
            ->put(route('workshop.profile.update'), ['name' => 'Oficina em análise'])
            ->assertRedirect(route('workshop.profile.show'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Oficina em análise', $user->fresh()->workshop->name);
    }

    public function test_approved_workshop_passes_through(): void
    {
        $user = $this->workshopUser(WorkshopStatus::Approved);

        foreach ($this->blockedRoutes() as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_an_account_without_a_workshop_keeps_the_onboarding_screens(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $user->workshop->delete();

        $this->actingAs($user->fresh())->get(route('workshop.dashboard'))->assertOk();
    }

    public function test_the_form_never_changes_the_status(): void
    {
        $user = $this->workshopUser(WorkshopStatus::Pending);

        $this->actingAs($user)->put(route('workshop.profile.update'), [
            'name' => 'Oficina do Dev',
            'status' => WorkshopStatus::Approved->value,
            'reviewed_at' => now()->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(WorkshopStatus::Pending, $user->fresh()->workshop->status);
        $this->assertNull($user->fresh()->workshop->reviewed_at);
    }

    public function test_rejected_workshop_sees_the_reason(): void
    {
        $this->actingAs($this->workshopUser(WorkshopStatus::Rejected, 'CNPJ baixado na Receita.'))
            ->get(route('workshop.profile.show'))
            ->assertOk()
            ->assertSee('CNPJ baixado na Receita.');
    }

    public function test_pending_workshop_lands_on_minha_oficina_and_sees_only_it_in_the_menu(): void
    {
        $user = $this->workshopUser(WorkshopStatus::Pending);

        // Sem isto o login mandaria para o dashboard, que o portão devolve: laço de redirecionamento.
        $this->assertSame('workshop.profile.show', Portal::Workshop->dashboardRouteFor($user));
        $this->assertNull(Portal::Workshop->primaryActionFor($user));

        $labels = array_column(
            Portal::Workshop->navigationItems(tap(request(), fn ($request) => $request->setUserResolver(fn () => $user))),
            'label',
        );

        $this->assertSame(['Minha oficina'], $labels);
    }

    public function test_approved_workshop_keeps_the_whole_menu(): void
    {
        $user = $this->workshopUser(WorkshopStatus::Approved);

        $this->assertSame('workshop.dashboard', Portal::Workshop->dashboardRouteFor($user));
        $this->assertNotNull(Portal::Workshop->primaryActionFor($user));
    }

    public function test_existing_workshops_were_grandfathered_as_approved(): void
    {
        // A migration marca 'approved' o que já existia; a factory segue a mesma regra.
        $this->assertTrue(User::factory()->asWorkshop()->create()->workshop->isApproved());
        $this->assertTrue(Workshop::factory()->create()->isApproved());
    }

    /**
     * O CNPJ é o que a equipe confere. "Minha oficina" não o edita, então a aprovação não pode ser
     * contornada trocando o documento depois. Se este teste falhar, o campo virou editável: copie o
     * App\Services\Garage\GarageReview::resetForDocumentChange do Lojista para a atualização.
     */
    public function test_the_form_cannot_change_the_cnpj(): void
    {
        $user = $this->workshopUser(WorkshopStatus::Approved);

        $this->actingAs($user)
            ->put(route('workshop.profile.update'), ['cnpj' => '98765432000198'])
            ->assertSessionHasNoErrors();

        $this->assertSame('12345678000190', $user->fresh()->workshop->cnpj);
        $this->assertTrue($user->fresh()->workshop->isApproved());
    }

    public function test_approving_notifies_the_account_and_opens_the_portal(): void
    {
        Notification::fake();

        $user = $this->workshopUser(WorkshopStatus::Pending);
        $admin = User::factory()->asAdmin()->create();

        app(WorkshopReview::class)->approve($user->workshop, $admin);

        Notification::assertSentTo($user, WorkshopApprovalDecidedNotification::class);
        $this->assertTrue($user->fresh()->workshop->isApproved());
        $this->assertSame($admin->id, $user->fresh()->workshop->reviewed_by);

        $this->actingAs($user->fresh())->get(route('workshop.dashboard'))->assertOk();
    }
}

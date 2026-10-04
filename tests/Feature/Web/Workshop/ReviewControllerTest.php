<?php

namespace Tests\Feature\Web\Workshop;

use App\Enums\WorkshopReviewStatus;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Notifications\WorkshopReviewDecidedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * ReviewController: fila "Validações" da oficina (confirmar / não reconhecer serviços declarados).
 */
class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $workshopUser;

    private Workshop $workshop;

    private User $owner;

    private Vehicle $vehicle;

    private Maintenance $pendingMaintenance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshopUser = User::factory()->asWorkshop()->create();
        $this->workshop = $this->workshopUser->workshop;

        $this->owner = User::factory()->asUser()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($this->owner, $this->vehicle);

        $this->pendingMaintenance = Maintenance::factory()->create([
            'user_id' => $this->owner->id,
            'vehicle_id' => $this->vehicle->id,
            'workshop_id' => $this->workshop->id,
            'workshop_name' => $this->workshop->name,
            'workshop_review_status' => WorkshopReviewStatus::Pending,
        ]);
    }

    // ── index ────────────────────────────────────────────────────────────────

    public function test_workshop_can_see_validation_queue(): void
    {
        $this->actingAs($this->workshopUser)
            ->get(route('workshop.reviews.index'))
            ->assertOk()
            ->assertSee('Validações');
    }

    public function test_pending_maintenance_appears_in_list(): void
    {
        $this->actingAs($this->workshopUser)
            ->get(route('workshop.reviews.index'))
            ->assertOk()
            ->assertSee($this->pendingMaintenance->maintenance_type);
    }

    public function test_empty_state_shown_when_no_pending(): void
    {
        $this->pendingMaintenance->forceFill(['workshop_review_status' => WorkshopReviewStatus::Confirmed])->save();

        $this->actingAs($this->workshopUser)
            ->get(route('workshop.reviews.index'))
            ->assertOk()
            ->assertSee('Nenhum serviço aguardando validação');
    }

    public function test_maintenance_from_another_workshop_not_visible(): void
    {
        $anotherWorkshopUser = User::factory()->asWorkshop()->create();
        $otherMaintenance = Maintenance::factory()->create([
            'workshop_id' => $anotherWorkshopUser->workshop->id,
            'workshop_review_status' => WorkshopReviewStatus::Pending,
        ]);

        $this->actingAs($this->workshopUser)
            ->get(route('workshop.reviews.index'))
            ->assertOk()
            ->assertDontSee($otherMaintenance->maintenance_type);
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $this->get(route('workshop.reviews.index'))
            ->assertRedirect();
    }

    public function test_owner_account_is_redirected_from_workshop_area(): void
    {
        // 403 de área errada é convertido em redirecionamento pelo FriendlyHttpErrors (bootstrap/app.php).
        $this->actingAs($this->owner)
            ->get(route('workshop.reviews.index'))
            ->assertRedirect();
    }

    public function test_redirects_to_profile_creation_when_workshop_not_set_up(): void
    {
        $workshopUserWithoutWorkshop = User::factory()->asWorkshop()->create();
        $workshopUserWithoutWorkshop->workshop->delete();
        $workshopUserWithoutWorkshop->unsetRelation('workshop');

        $this->actingAs($workshopUserWithoutWorkshop)
            ->get(route('workshop.reviews.index'))
            ->assertRedirect(route('workshop.profile.create'));
    }

    // ── confirm ──────────────────────────────────────────────────────────────

    public function test_workshop_can_confirm_a_pending_maintenance(): void
    {
        Notification::fake();

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.confirm', $this->pendingMaintenance))
            ->assertRedirect(route('workshop.reviews.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($this->pendingMaintenance->fresh()->verified_at);
        $this->assertSame(WorkshopReviewStatus::Confirmed, $this->pendingMaintenance->fresh()->workshop_review_status);
    }

    public function test_confirm_notifies_declarant(): void
    {
        Notification::fake();

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.confirm', $this->pendingMaintenance));

        Notification::assertSentTo($this->owner, WorkshopReviewDecidedNotification::class,
            fn (WorkshopReviewDecidedNotification $n) => $n->status === WorkshopReviewStatus::Confirmed
        );
    }

    public function test_cannot_confirm_another_workshops_maintenance(): void
    {
        $anotherWorkshopUser = User::factory()->asWorkshop()->create();

        $this->actingAs($anotherWorkshopUser)
            ->post(route('workshop.reviews.confirm', $this->pendingMaintenance))
            ->assertRedirect(route('workshop.reviews.index'))
            ->assertSessionHas('error');

        $this->assertNull($this->pendingMaintenance->fresh()->verified_at);
    }

    public function test_cannot_confirm_already_confirmed_maintenance(): void
    {
        $this->pendingMaintenance->forceFill(['workshop_review_status' => WorkshopReviewStatus::Confirmed])->save();

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.confirm', $this->pendingMaintenance))
            ->assertRedirect(route('workshop.reviews.index'))
            ->assertSessionHas('error');
    }

    // ── reject ───────────────────────────────────────────────────────────────

    public function test_workshop_can_reject_a_pending_maintenance(): void
    {
        Notification::fake();

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.reject', $this->pendingMaintenance), [
                'note' => 'Não reconhecemos este serviço.',
            ])
            ->assertRedirect(route('workshop.reviews.index'))
            ->assertSessionHas('success');

        $fresh = $this->pendingMaintenance->fresh();
        $this->assertNull($fresh->workshop_id);
        $this->assertSame(WorkshopReviewStatus::Rejected, $fresh->workshop_review_status);
        $this->assertSame('Não reconhecemos este serviço.', $fresh->workshop_review_note);
    }

    public function test_reject_notifies_declarant(): void
    {
        Notification::fake();

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.reject', $this->pendingMaintenance), ['note' => 'Motivo.']);

        Notification::assertSentTo($this->owner, WorkshopReviewDecidedNotification::class,
            fn (WorkshopReviewDecidedNotification $n) => $n->status === WorkshopReviewStatus::Rejected
        );
    }

    public function test_reject_without_note_is_allowed(): void
    {
        Notification::fake();

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.reject', $this->pendingMaintenance))
            ->assertRedirect(route('workshop.reviews.index'))
            ->assertSessionHas('success');

        $this->assertNull($this->pendingMaintenance->fresh()->workshop_review_note);
    }

    public function test_reject_note_exceeding_500_chars_fails_validation(): void
    {
        $this->actingAs($this->workshopUser)
            ->post(route('workshop.reviews.reject', $this->pendingMaintenance), [
                'note' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors(['note']);
    }

    public function test_cannot_reject_another_workshops_maintenance(): void
    {
        $anotherWorkshopUser = User::factory()->asWorkshop()->create();

        $this->actingAs($anotherWorkshopUser)
            ->post(route('workshop.reviews.reject', $this->pendingMaintenance))
            ->assertRedirect(route('workshop.reviews.index'))
            ->assertSessionHas('error');

        $this->assertSame($this->workshop->id, (int) $this->pendingMaintenance->fresh()->workshop_id);
    }
}

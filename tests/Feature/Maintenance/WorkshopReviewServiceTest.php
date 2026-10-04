<?php

namespace Tests\Feature\Maintenance;

use App\Enums\WorkshopReviewStatus;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Notifications\WorkshopReviewDecidedNotification;
use App\Notifications\WorkshopReviewRequestedNotification;
use App\Services\FcmService;
use App\Services\Maintenance\WorkshopReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * WorkshopReviewService: fila de validação de manutenções declaradas pela oficina citada.
 */
class WorkshopReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkshopReviewService $service;

    private User $workshopUser;

    private Workshop $workshop;

    private User $owner;

    private Vehicle $vehicle;

    private Maintenance $maintenance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkshopReviewService::class);

        $this->workshopUser = User::factory()->asWorkshop()->create();
        $this->workshop = $this->workshopUser->workshop;

        $this->owner = User::factory()->asUser()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($this->owner, $this->vehicle);
        $this->maintenance = Maintenance::factory()->create([
            'user_id' => $this->owner->id,
            'vehicle_id' => $this->vehicle->id,
            'workshop_id' => $this->workshop->id,
            'workshop_name' => $this->workshop->name,
            'workshop_review_status' => WorkshopReviewStatus::Pending,
        ]);
    }

    // ── syncAfterDeclaration ─────────────────────────────────────────────────

    public function test_sync_sends_review_request_when_new_workshop_is_assigned(): void
    {
        Notification::fake();

        $maintenance = Maintenance::factory()->create([
            'user_id' => $this->owner->id,
            'vehicle_id' => $this->vehicle->id,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->service->syncAfterDeclaration($maintenance->fresh(), previousWorkshopId: null);

        Notification::assertSentTo($this->workshopUser, WorkshopReviewRequestedNotification::class);
        $this->assertSame(WorkshopReviewStatus::Pending, $maintenance->fresh()->workshop_review_status);
    }

    public function test_sync_sends_review_request_when_workshop_changes(): void
    {
        Notification::fake();

        $anotherWorkshopUser = User::factory()->asWorkshop()->create();
        $this->maintenance->forceFill(['workshop_id' => $anotherWorkshopUser->workshop->id])->save();

        $this->service->syncAfterDeclaration($this->maintenance->fresh(), previousWorkshopId: $this->workshop->id);

        Notification::assertSentTo($anotherWorkshopUser, WorkshopReviewRequestedNotification::class);
        Notification::assertNotSentTo($this->workshopUser, WorkshopReviewRequestedNotification::class);
    }

    public function test_sync_clears_pending_status_when_workshop_is_removed(): void
    {
        $this->maintenance->forceFill(['workshop_id' => null])->save();

        $this->service->syncAfterDeclaration($this->maintenance->fresh(), previousWorkshopId: $this->workshop->id);

        $this->assertNull($this->maintenance->fresh()->workshop_review_status);
    }

    public function test_sync_does_nothing_when_already_verified(): void
    {
        Notification::fake();

        $this->maintenance->forceFill(['verified_at' => now()])->save();

        $this->service->syncAfterDeclaration($this->maintenance->fresh(), previousWorkshopId: null);

        Notification::assertNothingSent();
    }

    // ── confirm ──────────────────────────────────────────────────────────────

    public function test_confirm_applies_seal_and_marks_confirmed(): void
    {
        $sealed = $this->service->confirm($this->maintenance, $this->workshopUser);

        $this->assertNotNull($sealed->verified_at);
        $this->assertSame('confirmed', $sealed->verification_method);
        $this->assertSame($this->workshop->id, (int) $sealed->verified_workshop_id);
        $this->assertSame(WorkshopReviewStatus::Confirmed, $sealed->workshop_review_status);
        $this->assertSame($this->workshopUser->id, (int) $sealed->workshop_reviewed_by);
        $this->assertNotNull($sealed->workshop_reviewed_at);
    }

    public function test_confirm_notifies_declarant(): void
    {
        Notification::fake();

        $this->service->confirm($this->maintenance, $this->workshopUser);

        Notification::assertSentTo($this->owner, WorkshopReviewDecidedNotification::class,
            fn (WorkshopReviewDecidedNotification $n) => $n->status === WorkshopReviewStatus::Confirmed
        );
    }

    public function test_confirm_email_includes_workshop_name_in_body(): void
    {
        $this->workshop->forceFill(['name' => 'Oficina Teste'])->save();

        $notification = new WorkshopReviewDecidedNotification(
            $this->maintenance,
            WorkshopReviewStatus::Confirmed,
            'Oficina Teste',
        );

        $mail = $notification->toMail($this->owner);
        $rendered = $mail->render();

        $this->assertStringContainsString('Oficina Teste', $rendered);
    }

    public function test_reject_email_includes_workshop_name_in_body(): void
    {
        $notification = new WorkshopReviewDecidedNotification(
            $this->maintenance,
            WorkshopReviewStatus::Rejected,
            $this->workshop->name,
            'Não atendemos.',
        );

        $mail = $notification->toMail($this->owner);
        $rendered = $mail->render();

        $this->assertStringContainsString($this->workshop->name, $rendered);
        $this->assertStringContainsString('Não atendemos.', $rendered);
    }

    public function test_confirm_generates_verification_code(): void
    {
        $sealed = $this->service->confirm($this->maintenance, $this->workshopUser);

        $this->assertNotNull($sealed->verification_code);
        $this->assertStringStartsWith('RVL-', $sealed->verification_code);
    }

    // ── reject ───────────────────────────────────────────────────────────────

    public function test_reject_clears_workshop_link_and_records_rejector(): void
    {
        $result = $this->service->reject($this->maintenance, $this->workshopUser, 'Não atendemos este veículo nessa data.');

        $this->assertNull($result->workshop_id);
        $this->assertSame($this->workshop->id, (int) $result->rejected_workshop_id);
        $this->assertSame(WorkshopReviewStatus::Rejected, $result->workshop_review_status);
        $this->assertSame('Não atendemos este veículo nessa data.', $result->workshop_review_note);
        $this->assertNull($result->verified_at);
    }

    public function test_reject_notifies_declarant(): void
    {
        Notification::fake();

        $this->service->reject($this->maintenance, $this->workshopUser, 'Motivo qualquer.');

        Notification::assertSentTo($this->owner, WorkshopReviewDecidedNotification::class,
            fn (WorkshopReviewDecidedNotification $n) => $n->status === WorkshopReviewStatus::Rejected
                && $n->note === 'Motivo qualquer.'
        );
    }

    public function test_reject_works_without_note(): void
    {
        $result = $this->service->reject($this->maintenance, $this->workshopUser);

        $this->assertNull($result->workshop_review_note);
        $this->assertSame(WorkshopReviewStatus::Rejected, $result->workshop_review_status);
    }

    // ── requestReview ────────────────────────────────────────────────────────

    public function test_request_review_notifies_workshop_user(): void
    {
        Notification::fake();

        $maintenance = Maintenance::factory()->create([
            'user_id' => $this->owner->id,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->service->requestReview($maintenance);

        Notification::assertSentTo($this->workshopUser, WorkshopReviewRequestedNotification::class);
        $this->assertSame(WorkshopReviewStatus::Pending, $maintenance->fresh()->workshop_review_status);
    }

    public function test_request_review_records_request_time_and_pushes_to_workshop(): void
    {
        Notification::fake();

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendToUser')->once()->withArgs(
                fn (int $userId, string $title, string $body, array $data) => $userId === $this->workshopUser->id
                    && $data['type'] === 'workshop-review-requested'
                    && $data['maintenance_id'] === (string) $this->maintenance->id
            )->andReturnTrue();
        });

        $this->maintenance->forceFill(['workshop_review_reminded_at' => now()])->save();

        $this->service->requestReview($this->maintenance);

        $fresh = $this->maintenance->fresh();
        $this->assertNotNull($fresh->workshop_review_requested_at);
        $this->assertNull($fresh->workshop_review_reminded_at);
    }

    public function test_request_review_resets_previous_decision_fields(): void
    {
        $this->maintenance->forceFill([
            'workshop_reviewed_at' => now(),
            'workshop_reviewed_by' => $this->workshopUser->id,
            'workshop_review_note' => 'antes',
        ])->save();

        $this->service->requestReview($this->maintenance);

        $fresh = $this->maintenance->fresh();
        $this->assertNull($fresh->workshop_reviewed_at);
        $this->assertNull($fresh->workshop_reviewed_by);
        $this->assertNull($fresh->workshop_review_note);
    }
}

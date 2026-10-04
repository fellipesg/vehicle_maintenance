<?php

namespace Tests\Feature\Maintenance;

use App\Enums\WorkshopReviewStatus;
use App\Models\Maintenance;
use App\Models\User;
use App\Notifications\WorkshopReviewReminderNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * workshop-reviews:remind: um lembrete por oficina para os pedidos parados há 7 dias ou mais.
 */
class WorkshopReviewReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $workshopUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshopUser = User::factory()->asWorkshop()->create();
    }

    private function pending(int $daysAgo, ?User $workshopUser = null): Maintenance
    {
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => ($workshopUser ?? $this->workshopUser)->workshop->id,
        ]);

        $maintenance->forceFill([
            'workshop_review_status' => WorkshopReviewStatus::Pending,
            'workshop_review_requested_at' => now()->subDays($daysAgo),
        ])->save();

        return $maintenance;
    }

    public function test_reminds_workshop_once_with_total_pending(): void
    {
        Notification::fake();

        $stale = $this->pending(8);
        $this->pending(10);
        $this->pending(2);

        $this->artisan('workshop-reviews:remind')->assertSuccessful();

        Notification::assertSentToTimes($this->workshopUser, WorkshopReviewReminderNotification::class, 1);
        Notification::assertSentTo($this->workshopUser, WorkshopReviewReminderNotification::class,
            fn (WorkshopReviewReminderNotification $notification) => $notification->staleCount === 2 && $notification->pendingCount === 3
        );
        $this->assertNotNull($stale->fresh()->workshop_review_reminded_at);
    }

    public function test_does_not_remind_the_same_request_twice(): void
    {
        Notification::fake();

        $this->pending(8);

        $this->artisan('workshop-reviews:remind');
        $this->artisan('workshop-reviews:remind');

        Notification::assertSentToTimes($this->workshopUser, WorkshopReviewReminderNotification::class, 1);
    }

    public function test_recent_requests_are_not_reminded(): void
    {
        Notification::fake();

        $this->pending(6);

        $this->artisan('workshop-reviews:remind');

        Notification::assertNothingSent();
    }

    public function test_answered_requests_are_not_reminded(): void
    {
        Notification::fake();

        $this->pending(9)->forceFill(['workshop_review_status' => WorkshopReviewStatus::Confirmed, 'verified_at' => now()])->save();

        $this->artisan('workshop-reviews:remind');

        Notification::assertNothingSent();
    }

    public function test_each_workshop_gets_its_own_reminder(): void
    {
        Notification::fake();

        $otherWorkshopUser = User::factory()->asWorkshop()->create();
        $this->pending(8);
        $this->pending(8, $otherWorkshopUser);

        $this->artisan('workshop-reviews:remind');

        Notification::assertSentToTimes($this->workshopUser, WorkshopReviewReminderNotification::class, 1);
        Notification::assertSentToTimes($otherWorkshopUser, WorkshopReviewReminderNotification::class, 1);
    }

    public function test_sends_push_to_workshop(): void
    {
        Notification::fake();
        $this->pending(8);

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendToUser')->once()->withArgs(
                fn (int $userId, string $title, string $body, array $data) => $userId === $this->workshopUser->id
                    && $data['type'] === 'workshop-review-reminder'
            )->andReturnTrue();
        });

        $this->artisan('workshop-reviews:remind');
    }

    public function test_reminder_links_to_validation_queue(): void
    {
        $data = (new WorkshopReviewReminderNotification(1, 2))->toArray($this->workshopUser);

        $this->assertSame('workshop-review-reminder', $data['type']);
        $this->assertSame(route('workshop.reviews.index', absolute: false), $data['action_url']);
        $this->assertSame('2 serviços aguardam a sua validação', $data['title']);
    }
}

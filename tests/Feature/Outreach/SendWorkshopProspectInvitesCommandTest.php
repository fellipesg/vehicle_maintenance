<?php

namespace Tests\Feature\Outreach;

use App\Enums\WorkshopProspectStatus;
use App\Jobs\SendWorkshopProspectInvite;
use App\Models\EmailSuppression;
use App\Models\WorkshopProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendWorkshopProspectInvitesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['outreach.enabled' => true, 'outreach.daily_limit' => 10]);
        Cache::forget('outreach.paused');
        Queue::fake();
        // Quarta-feira, 11h em São Paulo.
        $this->travelTo(Carbon::parse('2026-10-07 14:00:00', 'UTC'));
    }

    public function test_it_dispatches_one_job_for_the_oldest_pending_prospect(): void
    {
        $first = WorkshopProspect::factory()->create();
        WorkshopProspect::factory()->create();

        $this->artisan('outreach:send')->assertSuccessful();

        Queue::assertPushed(SendWorkshopProspectInvite::class, 1);
        Queue::assertPushed(SendWorkshopProspectInvite::class, fn (SendWorkshopProspectInvite $job): bool => $job->prospectId === $first->id
            && $job->connection === 'database'
            && $job->delay instanceof \DateTimeInterface
            && $job->delay->getTimestamp() - now()->getTimestamp() <= 600);
    }

    public function test_it_does_nothing_when_disabled_or_paused(): void
    {
        WorkshopProspect::factory()->create();

        config(['outreach.enabled' => false]);
        $this->artisan('outreach:send')->assertSuccessful();

        config(['outreach.enabled' => true]);
        Cache::forever('outreach.paused', true);
        $this->artisan('outreach:send')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_it_respects_weekdays_and_the_time_window(): void
    {
        WorkshopProspect::factory()->create();

        $this->travelTo(Carbon::parse('2026-10-10 14:00:00', 'UTC'));
        $this->artisan('outreach:send')->assertSuccessful();

        $this->travelTo(Carbon::parse('2026-10-07 11:30:00', 'UTC'));
        $this->artisan('outreach:send')->assertSuccessful();

        $this->travelTo(Carbon::parse('2026-10-07 21:00:00', 'UTC'));
        $this->artisan('outreach:send')->assertSuccessful();

        Queue::assertNothingPushed();

        $this->travelTo(Carbon::parse('2026-10-07 12:30:00', 'UTC'));
        $this->artisan('outreach:send')->assertSuccessful();
        Queue::assertPushed(SendWorkshopProspectInvite::class, 1);
    }

    public function test_daily_limit_counts_first_touch_and_follow_ups_sent_today(): void
    {
        config(['outreach.daily_limit' => 3]);
        WorkshopProspect::factory()->count(2)->sent(now()->subHour())->create();
        WorkshopProspect::factory()->create([
            'status' => WorkshopProspectStatus::FollowedUp,
            'first_sent_at' => now()->subDays(12),
            'follow_up_sent_at' => now()->subMinutes(5),
        ]);
        WorkshopProspect::factory()->create();

        $this->artisan('outreach:send')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_sends_from_yesterday_in_sao_paulo_do_not_count_today(): void
    {
        config(['outreach.daily_limit' => 1]);
        WorkshopProspect::factory()->sent(Carbon::parse('2026-10-07 02:00:00', 'UTC'))->create();
        $pending = WorkshopProspect::factory()->create();

        $this->artisan('outreach:send')->assertSuccessful();

        Queue::assertPushed(SendWorkshopProspectInvite::class, fn ($job): bool => $job->prospectId === $pending->id);
    }

    public function test_it_picks_a_due_follow_up_before_a_pending_prospect(): void
    {
        WorkshopProspect::factory()->create();
        $due = WorkshopProspect::factory()->sent(now()->subDays(14))->create();
        WorkshopProspect::factory()->sent(now()->subDays(2))->create();

        $this->artisan('outreach:send')->assertSuccessful();

        Queue::assertPushed(SendWorkshopProspectInvite::class, fn ($job): bool => $job->prospectId === $due->id);
    }

    public function test_it_skips_pending_prospects_that_became_suppressed(): void
    {
        $suppressed = WorkshopProspect::factory()->create(['email' => 'fora@x.com.br']);
        $next = WorkshopProspect::factory()->create();
        EmailSuppression::suppress('fora@x.com.br', 'manual');

        $this->artisan('outreach:send')->assertSuccessful();

        Queue::assertPushed(SendWorkshopProspectInvite::class, fn ($job): bool => $job->prospectId === $next->id);
        $this->assertSame(WorkshopProspectStatus::Skipped, $suppressed->fresh()->status);
    }

    public function test_dry_run_prints_the_choice_and_dispatches_nothing(): void
    {
        config(['outreach.enabled' => false]);
        $prospect = WorkshopProspect::factory()->create(['trade_name' => 'Auto Zé']);

        $this->artisan('outreach:send', ['--dry-run' => true])
            ->expectsOutputToContain('Enviaria primeiro contato para Auto Zé')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(WorkshopProspectStatus::Pending, $prospect->fresh()->status);
    }

    public function test_the_command_is_scheduled_every_fifteen_minutes_on_weekdays(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'outreach:send'));

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * 1-5', $event->expression);
        $this->assertSame('America/Sao_Paulo', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_pending_jobs_on_the_database_queue_count_even_when_the_default_connection_differs(): void
    {
        config(['queue.default' => 'sync', 'outreach.daily_limit' => 1]);
        \Illuminate\Support\Facades\DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => SendWorkshopProspectInvite::class]),
            'attempts' => 0,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        WorkshopProspect::factory()->create();

        $this->artisan('outreach:send')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}

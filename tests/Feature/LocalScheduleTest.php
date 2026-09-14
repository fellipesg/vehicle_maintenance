<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class LocalScheduleTest extends TestCase
{
    public function test_local_schedule_includes_queue_worker_every_thirty_seconds(): void
    {
        $events = app(Schedule::class)->events();

        $queueWorker = collect($events)->first(
            fn (Event $event): bool => str_contains((string) ($event->command ?? ''), 'queue:work --stop-when-empty --max-time=25 --tries=1')
        );

        $this->assertNotNull($queueWorker, 'Expected queue:work schedule for local PDF export draining');
        $this->assertTrue($queueWorker->isRepeatable());
        $this->assertSame(30, $queueWorker->repeatSeconds);
        $this->assertTrue($queueWorker->runsInEnvironment('local'));
        $this->assertFalse($queueWorker->runsInEnvironment('production'));
    }

    public function test_daily_scheduled_tasks_are_registered(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn (Event $event): string => (string) ($event->command ?? ''))
            ->all();

        $this->assertTrue(
            collect($commands)->contains(fn (string $command): bool => str_contains($command, 'sanctum:prune-expired')),
        );
        $this->assertTrue(
            collect($commands)->contains(fn (string $command): bool => str_contains($command, 'maintenance:check-km-reminders')),
        );
        $this->assertTrue(
            collect($commands)->contains(fn (string $command): bool => str_contains($command, 'vehicle-pdf-exports:cleanup')),
        );
    }
}

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

    /**
     * Sem prune a telescope_entries cresce sem teto no Neon. O agendamento fica atrás de um
     * class_exists porque o pacote está em require-dev e um deploy com --no-dev não o tem.
     */
    public function test_telescope_prune_is_scheduled_while_the_package_is_installed(): void
    {
        if (! class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
            $this->markTestSkipped('laravel/telescope não está instalado neste ambiente.');
        }

        $prune = collect(app(Schedule::class)->events())->first(
            fn (Event $event): bool => str_contains((string) ($event->command ?? ''), 'telescope:prune')
        );

        $this->assertNotNull($prune, 'Expected telescope:prune to be scheduled');
        $this->assertStringContainsString('--hours=48', (string) $prune->command);
        $this->assertSame('0 0 * * *', $prune->expression);
        $this->assertTrue($prune->runsInEnvironment('production'));
    }
}

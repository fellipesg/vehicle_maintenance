<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('maintenance:check-km-reminders')->dailyAt('08:00');
Schedule::command('vehicle-pdf-exports:cleanup')->daily();

Schedule::command('queue:work --stop-when-empty --max-time=25 --tries=1')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->environments(['local']);

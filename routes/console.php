<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('maintenance:check-km-reminders')->dailyAt('08:00');
Schedule::command('workshop-reviews:remind')->dailyAt('09:00');
Schedule::command('vehicle-pdf-exports:cleanup')->hourly()->withoutOverlapping();
Schedule::command('maintenance:purge-pending-attachments')->dailyAt('03:30');

Schedule::command('queue:work --stop-when-empty --max-time=25 --tries=1')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->environments(['local']);

Schedule::command('outreach:send')
    ->everyFifteenMinutes()
    ->weekdays()
    ->between('9:00', '17:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

/*
 * O Telescope está em require-dev, então um deploy com --no-dev não tem o pacote e não tem o
 * comando. O mesmo class_exists de bootstrap/providers.php decide aqui: onde o pacote existe
 * (local, CI, e qualquer deploy que instale as dev deps) o prune é agendado; onde não existe,
 * nada é registrado e o scheduler não quebra com "command not found".
 *
 * O prune não olha telescope.enabled de propósito: desligar a gravação não apaga o que já está
 * em telescope_entries, e sem prune a tabela cresce sem teto no Neon.
 */
if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
    Schedule::command('telescope:prune --hours=48')->daily();
}

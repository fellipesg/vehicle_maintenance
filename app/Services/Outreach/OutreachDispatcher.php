<?php

namespace App\Services\Outreach;

use App\Enums\WorkshopProspectStatus;
use App\Jobs\SendWorkshopProspectInvite;
use App\Models\EmailSuppression;
use App\Models\WorkshopProspect;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decide se e quem recebe o próximo envio de prospecção. Usado por outreach:send e pelo painel admin.
 */
class OutreachDispatcher
{
    public const PAUSE_CACHE_KEY = 'outreach.paused';

    public function isPaused(): bool
    {
        return (bool) Cache::get(self::PAUSE_CACHE_KEY, false);
    }

    public function pause(): void
    {
        Cache::forever(self::PAUSE_CACHE_KEY, true);
    }

    public function resume(): void
    {
        Cache::forget(self::PAUSE_CACHE_KEY);
    }

    public function isActive(): bool
    {
        return (bool) config('outreach.enabled') && ! $this->isPaused();
    }

    /**
     * Dentro da janela de envio: dia útil, entre window.start e window.end no fuso configurado.
     */
    public function isWithinWindow(?Carbon $at = null): bool
    {
        $local = ($at ?? now())->copy()->setTimezone((string) config('outreach.window.timezone'));

        if ($local->isWeekend()) {
            return false;
        }

        $time = $local->format('H:i');

        return $time >= config('outreach.window.start') && $time <= config('outreach.window.end');
    }

    /**
     * E-mails enviados hoje (primeiro contato e follow-up, no dia de São Paulo) mais os já na fila.
     */
    public function sentToday(): int
    {
        $timezone = (string) config('outreach.window.timezone');
        $start = now()->setTimezone($timezone)->startOfDay()->setTimezone(config('app.timezone'));
        $end = $start->copy()->addDay();

        $sent = WorkshopProspect::query()->whereBetween('first_sent_at', [$start, $end])->count()
            + WorkshopProspect::query()->whereBetween('follow_up_sent_at', [$start, $end])->count();

        return $sent + $this->queuedJobs();
    }

    public function remainingToday(): int
    {
        return max(0, (int) config('outreach.daily_limit') - $this->sentToday());
    }

    /**
     * Follow-ups vencidos primeiro, depois o pendente mais antigo. Pendente cujo e-mail entrou na
     * lista de supressão vira Skipped e não é escolhido.
     */
    public function pickNext(): ?WorkshopProspect
    {
        $followUps = WorkshopProspect::query()
            ->where('status', WorkshopProspectStatus::Sent)
            ->whereNull('follow_up_sent_at')
            ->whereNotNull('first_sent_at')
            ->where('first_sent_at', '<=', now()->subDays((int) config('outreach.follow_up_after_business_days')))
            ->orderBy('first_sent_at')
            ->orderBy('id')
            ->lazyById();

        foreach ($followUps as $prospect) {
            if ($prospect->isFollowUpDue() && ! EmailSuppression::isSuppressed($prospect->email)) {
                return $prospect;
            }
        }

        $pending = WorkshopProspect::query()
            ->where('status', WorkshopProspectStatus::Pending)
            ->orderBy('id')
            ->lazyById();

        foreach ($pending as $prospect) {
            if (EmailSuppression::isSuppressed($prospect->email)) {
                $prospect->update(['status' => WorkshopProspectStatus::Skipped]);

                continue;
            }

            return $prospect;
        }

        return null;
    }

    /**
     * O job sempre usa a conexão database, qualquer que seja QUEUE_CONNECTION.
     */
    private function queuedJobs(): int
    {
        $connection = config('queue.connections.database', []);
        $table = $connection['table'] ?? 'jobs';
        $schema = Schema::connection($connection['connection'] ?? null);

        if (! $schema->hasTable($table)) {
            return 0;
        }

        return DB::connection($connection['connection'] ?? null)
            ->table($table)
            ->where('payload', 'like', '%'.class_basename(SendWorkshopProspectInvite::class).'%')
            ->count();
    }
}

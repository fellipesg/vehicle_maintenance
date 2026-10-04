<?php

namespace App\Console\Commands;

use App\Jobs\SendWorkshopProspectInvite;
use App\Services\Outreach\OutreachDispatcher;
use Illuminate\Console\Command;

class SendWorkshopProspectInvites extends Command
{
    protected $signature = 'outreach:send {--dry-run : Mostra o que enviaria, sem enfileirar (ignora ligado/pausado e a janela)}';

    protected $description = 'Enfileira o próximo e-mail de prospecção de oficinas (no máximo um por execução)';

    public function handle(OutreachDispatcher $dispatcher): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun) {
            if (! config('outreach.enabled')) {
                $this->line('Prospecção desligada (OUTREACH_ENABLED).');

                return self::SUCCESS;
            }

            if ($dispatcher->isPaused()) {
                $this->line('Prospecção pausada no painel.');

                return self::SUCCESS;
            }

            if (! $dispatcher->isWithinWindow()) {
                $this->line('Fora da janela de envio.');

                return self::SUCCESS;
            }
        }

        if ($dispatcher->remainingToday() <= 0) {
            $this->line('Limite diário atingido.');

            return self::SUCCESS;
        }

        $prospect = $dispatcher->pickNext();
        if ($prospect === null) {
            $this->line('Ninguém para enviar agora.');

            return self::SUCCESS;
        }

        $touch = $prospect->status->value === 'pending' ? 'primeiro contato' : 'follow-up';

        if ($dryRun) {
            $this->info("Enviaria {$touch} para {$prospect->displayName()} <{$prospect->email}>.");

            return self::SUCCESS;
        }

        SendWorkshopProspectInvite::dispatch($prospect->id)->delay(now()->addSeconds(random_int(0, 600)));
        $this->info("Enfileirado {$touch} para {$prospect->displayName()}.");

        return self::SUCCESS;
    }
}

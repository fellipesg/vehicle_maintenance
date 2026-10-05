<?php

namespace App\Console\Commands;

use App\Mail\WorkshopProspectInviteMail;
use App\Models\WorkshopProspect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Manda o convite de prospecção de verdade (mailer de outreach) para endereços de teste, sem gravar
 * prospecto, sem passar pela fila e sem contar no limite diário. Funciona com a prospecção desligada.
 */
class SendWorkshopProspectTestInvite extends Command
{
    protected $signature = 'outreach:send-test
        {emails* : Um ou mais e-mails que recebem o teste}
        {--follow-up : Envia o follow-up em vez do primeiro convite}
        {--name=Auto Center Exemplo : Nome fantasia da oficina de exemplo; vazio testa o texto sem nome}';

    protected $description = 'Envia o convite de prospecção para e-mails de teste pelo mailer de outreach';

    public function handle(): int
    {
        $variant = $this->option('follow-up') ? WorkshopProspectInviteMail::FOLLOW_UP : WorkshopProspectInviteMail::FIRST_TOUCH;
        $mailer = (string) config('outreach.mailer');
        $failures = 0;

        foreach ((array) $this->argument('emails') as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error("E-mail inválido: {$email}");
                $failures++;

                continue;
            }

            $prospect = new WorkshopProspect([
                'cnpj' => '00000000000000',
                'trade_name' => $this->option('name') ?: null,
                'email' => $email,
                'token' => Str::random(40),
                'city' => 'LONDRINA',
                'state' => 'PR',
            ]);

            try {
                Mail::mailer($mailer)->to($email)->send(new WorkshopProspectInviteMail($prospect, $variant));
                $this->info("Enviado para {$email} pelo mailer {$mailer}.");
            } catch (Throwable $exception) {
                $this->error("Falhou para {$email}: {$exception->getMessage()}");
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}

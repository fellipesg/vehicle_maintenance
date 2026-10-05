<?php

namespace App\Console\Commands;

use App\Mail\IosAppLaunchedMail;
use App\Models\User;
use App\Models\UserAnnouncement;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisa uma única vez os usuários cadastrados que o app iOS está na App Store. Oficinas ficam de fora
 * (o app ainda não tem a área delas), assim como contas de teste e anonimizadas. Rodar de novo só envia
 * para quem ainda não recebeu.
 */
class AnnounceIosApp extends Command
{
    /**
     * Domínios que nunca recebem e-mail real (seeders, testes e contas excluídas).
     *
     * @var list<string>
     */
    public const RESERVED_DOMAIN_SUFFIXES = ['.test', '.invalid', '.localhost', '.example', '@example.com', '@example.org', '@example.net'];

    protected $signature = 'users:announce-ios-app
        {--dry-run : Só lista quantos receberiam, sem enviar}
        {--pause-ms=600 : Pausa entre envios, para respeitar o limite de envio do provedor}';

    protected $description = 'Envia o aviso de lançamento do app iOS para os usuários cadastrados (uma vez por usuário)';

    public function handle(): int
    {
        $recipients = $this->recipients()->orderBy('id')->get();

        if ($this->option('dry-run')) {
            $this->info("Receberiam o aviso: {$recipients->count()} usuário(s). Nada foi enviado.");

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $user) {
            try {
                Mail::to($user->email)->send(new IosAppLaunchedMail($user));
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Falhou para o usuário #{$user->id}: {$exception->getMessage()}");

                continue;
            }

            UserAnnouncement::query()->create([
                'user_id' => $user->id,
                'announcement' => UserAnnouncement::IOS_APP_LAUNCH,
                'sent_at' => now(),
            ]);
            $sent++;

            usleep(max(0, (int) $this->option('pause-ms')) * 1000);
        }

        $this->info("Avisos enviados: {$sent}. Falhas: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Builder<User>
     */
    private function recipients(): Builder
    {
        return User::query()
            ->where('user_type', '!=', 'workshop')
            ->whereNotNull('email')
            ->whereDoesntHave('announcements', fn (Builder $query) => $query->where('announcement', UserAnnouncement::IOS_APP_LAUNCH))
            ->where(function (Builder $query): void {
                foreach (self::RESERVED_DOMAIN_SUFFIXES as $suffix) {
                    $query->whereRaw('lower(email) not like ?', ['%'.$suffix]);
                }
            });
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Aviso para o endereço ANTIGO quando o e-mail de login muda em "Minha conta" (/conta).
 *
 * Vai para o e-mail anterior (Notification::route('mail', ...)): se a troca não foi do dono, é por
 * ali que ele fica sabendo, já que o link de redefinição de senha passa a ir para o endereço novo.
 * O endereço novo aparece mascarado.
 */
class AccountEmailChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $accountName,
        public string $newEmail,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = Str::before(trim($this->accountName), ' ');

        return (new MailMessage)
            ->subject('O e-mail da sua conta na RevisaLog foi alterado')
            ->replyTo(
                (string) config('mail.reply_to.address'),
                (string) config('mail.reply_to.name'),
            )
            ->greeting($firstName !== '' ? "Olá, {$firstName}!" : 'Olá!')
            ->line('O e-mail de acesso da sua conta na RevisaLog foi trocado para '.self::maskEmail($this->newEmail).'. A partir de agora, o login e o link de redefinição de senha usam o endereço novo.')
            ->line('Se foi você, não precisa fazer nada.')
            ->line('Se não foi você, responda este e-mail para falarmos com você e recuperar o acesso.')
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account-email-changed',
        ];
    }

    /**
     * "ana.lima@example.com" vira "an***@example.com".
     */
    public static function maskEmail(string $email): string
    {
        $localPart = Str::before($email, '@');
        $domain = Str::after($email, '@');

        return Str::substr($localPart, 0, min(2, max(1, Str::length($localPart) - 1))).'***@'.$domain;
    }
}

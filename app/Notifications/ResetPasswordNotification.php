<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * E-mail de redefinição de senha em pt-BR, no chrome de e-mail do projeto (vendor/mail).
 *
 * Usa um markdown próprio (emails.password-reset) porque o template padrão das notificações
 * escreve a linha "If you're having trouble clicking..." em inglês. Vai para a fila, como o
 * e-mail de boas-vindas, para a resposta do pedido não demorar mais quando a conta existe.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresInMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);

        return (new MailMessage)
            ->subject('Redefinir sua senha na RevisaLog')
            ->replyTo(
                (string) config('mail.reply_to.address'),
                (string) config('mail.reply_to.name'),
            )
            ->markdown('emails.password-reset', [
                'firstName' => $this->firstName($notifiable),
                'resetUrl' => $this->resetUrl($notifiable),
                'expiresInMinutes' => $expiresInMinutes,
            ])
            ->salutation('RevisaLog');
    }

    public function resetUrl(object $notifiable): string
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'password-reset',
        ];
    }

    private function firstName(object $notifiable): string
    {
        $name = trim((string) ($notifiable->name ?? ''));

        return $name === '' ? '' : Str::before($name, ' ');
    }
}

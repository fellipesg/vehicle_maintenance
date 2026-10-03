<?php

namespace App\Notifications;

use App\Enums\RegistrationSource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserSignupAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
        public RegistrationSource $source,
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
        return (new MailMessage)
            ->theme('revisalog')
            ->from('noreply@revisalog.com.br', 'RevisaLog')
            ->subject("Novo cadastro — {$this->user->name}")
            ->greeting('Novo cadastro na RevisaLog')
            ->line("**Nome:** {$this->user->name}")
            ->line("**E-mail:** {$this->user->email}")
            ->line("**Perfil:** {$this->user->typeLabel()}")
            ->line('**Origem:** '.self::sourceLabel($this->source))
            ->salutation('RevisaLog');
    }

    /**
     * Por onde a conta foi criada, como o suporte lê.
     */
    public static function sourceLabel(RegistrationSource $source): string
    {
        return match ($source) {
            RegistrationSource::Web => 'Site',
            RegistrationSource::Api => 'App (e-mail e senha)',
            RegistrationSource::Oauth => 'App (Google ou Facebook)',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new-user-signup',
            'name' => $this->user->name,
            'email' => $this->user->email,
            'user_type' => $this->user->user_type,
            'source' => $this->source->value,
        ];
    }
}

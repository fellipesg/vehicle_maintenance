<?php

namespace App\Notifications;

use App\Enums\RegistrationSource;
use App\Models\User;
use App\Models\WorkshopProspect;
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
        $workshop = $this->user->user_type === 'workshop' ? $this->user->workshop : null;

        $message = (new MailMessage)
            ->theme('revisalog')
            ->from('noreply@revisalog.com.br', 'RevisaLog')
            ->subject($workshop === null
                ? "Novo cadastro — {$this->user->name}"
                : "Novo cadastro de oficina — {$workshop->name}")
            ->greeting($workshop === null ? 'Novo cadastro na RevisaLog' : 'Nova oficina na RevisaLog')
            ->line("**Nome:** {$this->user->name}")
            ->line("**E-mail:** {$this->user->email}")
            ->line("**Perfil:** {$this->user->typeLabel()}");

        if ($workshop !== null) {
            $message
                ->line("**Oficina:** {$workshop->name}")
                ->line('**CNPJ:** '.($workshop->cnpj !== null ? WorkshopProspect::formatCnpj($workshop->cnpj) : 'não informado'))
                ->line("**Cidade:** {$workshop->city}/{$workshop->state}")
                ->line("**Telefone:** {$workshop->phone}");
        }

        return $message
            ->line('**Origem:** '.self::sourceLabel($this->source, $workshop !== null))
            ->salutation('RevisaLog');
    }

    /**
     * Por onde a conta foi criada, como o suporte lê.
     */
    public static function sourceLabel(RegistrationSource $source, bool $workshop = false): string
    {
        return match ($source) {
            RegistrationSource::Web => $workshop ? 'Site (cadastro próprio de oficina)' : 'Site',
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

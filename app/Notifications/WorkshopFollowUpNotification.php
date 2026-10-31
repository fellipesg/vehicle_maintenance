<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkshopMessageTemplate;
use App\Support\Workshop\WorkshopMessageConsent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * Mensagem que a oficina manda ao dono atual de um veículo que ela atendeu, com título e corpo
 * escritos pela oficina. Respeita workshop_message_opt_outs (via WorkshopMessageConsent) em todos
 * os canais, e o e-mail leva List-Unsubscribe de um clique para a rota assinada de descadastro.
 */
class WorkshopFollowUpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public WorkshopMessageTemplate $template,
        public Vehicle $vehicle,
        public string $renderedTitle,
        public string $renderedBody,
        public array $context,
        public ?int $nextDueKilometers = null,
        public ?int $sourceMaintenanceId = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail', 'database'];
        }

        $consent = app(WorkshopMessageConsent::class);

        if (! $consent->allows($notifiable->id, $this->template->workshop_id)) {
            return [];
        }

        return $consent->allowsEmailTo($notifiable)
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ownerName = $this->ownerFirstName($notifiable);

        [$replyToAddress, $replyToName] = $this->replyTo();

        $mail = (new MailMessage)
            ->subject($this->renderedTitle)
            ->replyTo($replyToAddress, $replyToName)
            ->greeting("Olá, {$ownerName}!")
            ->line($this->renderedBody)
            ->action('Ver veículo', $this->vehicleUrl($notifiable));

        $unsubscribeUrl = $this->unsubscribeUrl($notifiable);

        if ($unsubscribeUrl !== null) {
            $mail->line($this->disclosure())
                ->line("Não quer mais mensagens desta oficina? [Descadastrar]({$unsubscribeUrl}).")
                ->withSymfonyMessage(function (Email $message) use ($unsubscribeUrl): void {
                    $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribeUrl.'>');
                    $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                });
        }

        return $mail->salutation('RevisaLog');
    }

    /**
     * Null quando o destinatário não é uma conta (o descadastro é por user_id e oficina).
     */
    public function unsubscribeUrl(object $notifiable): ?string
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        return app(WorkshopMessageConsent::class)
            ->unsubscribeUrl($notifiable->id, $this->template->workshop_id);
    }

    private function disclosure(): string
    {
        $workshopName = $this->context['workshop_name'] ?? $this->template->workshop?->name;

        return 'Você recebeu esta mensagem porque a oficina '.trim((string) $workshopName)
            .' registrou um serviço no seu veículo no RevisaLog.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workshop-follow-up',
            'title' => $this->renderedTitle,
            'body' => $this->renderedBody,
            'template_id' => $this->template->id,
            'workshop_id' => $this->template->workshop_id,
            'workshop_name' => $this->context['workshop_name'] ?? $this->template->workshop->name,
            'trigger' => $this->template->trigger->value,
            'vehicle_id' => $this->vehicle->id,
            'license_plate' => $this->vehicle->license_plate,
            'brand' => $this->vehicle->brand,
            'model' => $this->vehicle->model,
            'estimated_km' => $this->context['estimated_km'] ?? null,
            'next_due_kilometers' => $this->nextDueKilometers,
            'source_maintenance_id' => $this->sourceMaintenanceId,
            'vehicle_url' => $this->vehicleUrl($notifiable, absolute: false),
        ];
    }

    private function vehicleUrl(object $notifiable, bool $absolute = true): string
    {
        $routeName = ($notifiable instanceof User && $notifiable->isGarage())
            ? 'garage.vehicles.show'
            : 'user.vehicles.show';

        return route($routeName, $this->vehicle, absolute: $absolute);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function replyTo(): array
    {
        $this->template->loadMissing('workshop');

        $workshop = $this->template->workshop;
        $workshopEmail = is_string($workshop?->email) ? trim($workshop->email) : '';

        if ($workshopEmail !== '' && filter_var($workshopEmail, FILTER_VALIDATE_EMAIL) !== false) {
            return [$workshopEmail, (string) $workshop->name];
        }

        return [
            (string) config('mail.reply_to.address'),
            (string) config('mail.reply_to.name'),
        ];
    }

    private function ownerFirstName(object $notifiable): string
    {
        if (! $notifiable instanceof User) {
            return 'motorista';
        }

        $firstName = trim(explode(' ', trim($notifiable->name), 2)[0] ?? '');

        return $firstName !== '' ? $firstName : 'motorista';
    }
}

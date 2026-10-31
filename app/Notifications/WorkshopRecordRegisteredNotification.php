<?php

namespace App\Notifications;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Uma oficina registrou um serviço num veículo que já tem proprietário cadastrado. O registro nasce
 * com o Selo da oficina e entra no histórico do chassi na hora — o histórico é o ativo do produto e
 * não espera ninguém. O que o proprietário precisa é **saber**, e poder contestar se o serviço não
 * foi feito no carro dele (MaintenanceOwnerDecisionService::dispute).
 *
 * Não confundir com WorkshopRecordsPendingNotification, que avisa quem acabou de assumir um veículo
 * que já tinha registros de oficina esperando decisão (carro que não tinha dono na época).
 */
class WorkshopRecordRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'workshop_record_registered';

    public function __construct(public Maintenance $maintenance)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = Str::before(trim((string) ($notifiable->name ?? '')), ' ');

        return (new MailMessage)
            ->subject($this->title())
            ->replyTo(
                (string) config('mail.reply_to.address'),
                (string) config('mail.reply_to.name'),
            )
            ->greeting($firstName !== '' ? "Olá, {$firstName}!" : 'Olá!')
            ->line($this->body())
            ->line('O registro já faz parte do histórico do veículo, com o Selo da oficina. Se este serviço não foi feito no seu carro, abra o registro e use "contestar": ele continua visível, marcado como contestado por você, e a nossa equipe analisa.')
            ->action('Ver o registro', route('user.maintenances.show', $this->maintenance))
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => self::TYPE,
            'title' => $this->title(),
            'body' => $this->body(),
            'maintenance_id' => $this->maintenance->id,
            'vehicle_id' => $this->maintenance->vehicle_id,
        ];
    }

    public function title(): string
    {
        return 'Uma oficina registrou um serviço no seu veículo';
    }

    public function body(): string
    {
        $vehicle = $this->maintenance->vehicle;
        $label = $vehicle === null ? '' : trim($vehicle->brand.' '.$vehicle->model);
        $workshop = $this->maintenance->displayWorkshopName() ?? 'Uma oficina';

        return $workshop.' registrou "'.$this->maintenance->maintenance_type.'"'
            .($label !== '' ? ' no seu '.$label : '')
            .' em '.($this->maintenance->maintenance_date?->format('d/m/Y') ?? 'data não informada').'.';
    }
}

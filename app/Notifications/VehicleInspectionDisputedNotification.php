<?php

namespace App\Notifications;

use App\Models\VehicleInspection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * O proprietário do veículo contestou um laudo que esta oficina emitiu
 * (App\Services\Inspection\InspectionDisputeService::dispute). Vai para a conta da oficina porque ela
 * é a única que pode corrigir ou excluir o laudo (VehicleInspectionPolicy) — sem este aviso a
 * contestação ficaria invisível para quem tem o poder de resolvê-la, como em
 * MaintenanceDisputedNotification.
 *
 * O texto diz o que de fato acontece: o laudo **continua** em todas as superfícies, com a marca da
 * contestação junto. Prometer que ele sai de circulação enquanto a contestação está aberta seria
 * descrever o produto que o RevisaLog deliberadamente não é.
 */
class VehicleInspectionDisputedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'inspection_disputed';

    public function __construct(
        public VehicleInspection $inspection,
        public ?string $reason = null,
    ) {
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

        $message = (new MailMessage)
            ->subject($this->title())
            ->replyTo(
                (string) config('mail.reply_to.address'),
                (string) config('mail.reply_to.name'),
            )
            ->greeting($firstName !== '' ? "Olá, {$firstName}!" : 'Olá!')
            ->line($this->body());

        if (filled($this->reason)) {
            $message->line('O que o proprietário informou: '.$this->reason);
        }

        return $message
            ->line('O laudo continua no histórico do veículo e na conferência pública, agora com a contestação à vista de quem o abrir. Se foi engano — chassi ou placa de outro veículo, eixo marcado errado —, corrija ou exclua o laudo.')
            ->action('Abrir o laudo', route('workshop.inspections.show', $this->inspection))
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
            'inspection_id' => $this->inspection->id,
            'vehicle_id' => $this->inspection->vehicle_id,
            'reason' => $this->reason,
        ];
    }

    public function title(): string
    {
        return 'Um proprietário contestou um laudo seu';
    }

    public function body(): string
    {
        $vehicle = $this->inspection->vehicle;
        $label = $vehicle === null ? '' : trim($vehicle->brand.' '.$vehicle->model);

        return 'O proprietário de '.($label !== '' ? $label : 'um veículo')
            .' discorda da vistoria cautelar de '
            .($this->inspection->inspected_at?->format('d/m/Y') ?? 'data não informada')
            .' ('.($this->inspection->verdict?->label() ?? 'sem veredito').').';
    }
}

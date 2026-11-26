<?php

namespace App\Notifications;

use App\Models\VehicleInspection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Uma oficina emitiu um laudo de vistoria cautelar sobre um veículo que já tem proprietário
 * cadastrado. É a irmã de WorkshopRecordRegisteredNotification, e existe pelo mesmo motivo: o
 * documento já entrou no histórico do chassi, com código de conferência pública, e o que o
 * proprietário precisa é **saber** — e poder contestar, se a afirmação não corresponde ao carro
 * dele (App\Services\Inspection\InspectionDisputeService).
 *
 * Sem este aviso a metade de descoberta do par faltava: a contestação existia desde o PR #78, mas o
 * dono só encontrava um "Reprovado" sobre o próprio carro se abrisse a aba Vistorias por iniciativa
 * própria. Um laudo afirma a condição do veículo de alguém a quem está comprando; publicar isso sem
 * avisar quem é afirmado deixa a alavanca de resposta sem a alavanca de descoberta.
 *
 * O texto **não** promete que o laudo sai de circulação enquanto a contestação está aberta: não sai.
 * Contestar marca e não esconde (.ai/rules/inspections.md), e é o mesmo cuidado que
 * MaintenanceDisputedNotification já carrega (.ai/rules/notifications.md).
 *
 * Não confere workshop_message_opt_outs de propósito: ver o docblock de
 * App\Services\Maintenance\WorkshopRecordsArrivalNotifier::notifyOwnerOfInspection.
 */
class VehicleInspectionIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'inspection_issued';

    public function __construct(public VehicleInspection $inspection)
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
            ->line('O laudo já faz parte do histórico do veículo e pode ser conferido pelo código '.$this->inspection->verification_code.'. Se ele não corresponde ao seu carro, abra a aba Vistorias e use "contestar": o laudo continua visível, agora marcado como contestado por você, e a oficina que o emitiu é avisada.')
            ->action('Ver o laudo', $this->url())
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
        ];
    }

    public function title(): string
    {
        return 'Uma oficina emitiu um laudo de vistoria sobre o seu veículo';
    }

    /**
     * `loadMissing`, não `$this->inspection->vehicle`: a notificação é `ShouldQueue`, e o
     * SerializesModels devolve o laudo **sem** relações na fila — com Model::preventLazyLoading
     * ligado fora de produção, o acesso direto estouraria dentro do job em vez de mandar o e-mail.
     */
    public function body(): string
    {
        $this->inspection->loadMissing('vehicle');
        $vehicle = $this->inspection->getRelationValue('vehicle');
        $label = $vehicle === null ? '' : trim($vehicle->brand.' '.$vehicle->model);
        $workshop = filled($this->inspection->workshop_name)
            ? (string) $this->inspection->workshop_name
            : 'Uma oficina';

        return $workshop.' emitiu um laudo de vistoria cautelar'
            .($label !== '' ? ' no seu '.$label : '')
            .' em '.($this->inspection->inspected_at?->format('d/m/Y') ?? 'data não informada')
            .'.'.($this->inspection->verdict === null ? '' : ' Resultado: '.$this->inspection->verdict->label().'.');
    }

    /**
     * A aba Vistorias da ficha é onde o dono encontra o laudo e o botão de contestar
     * (.ai/rules/inspections.md). Sem veículo não há ficha: o link cai na lista de veículos.
     */
    private function url(): string
    {
        $vehicleId = $this->inspection->vehicle_id;

        return $vehicleId === null
            ? route('user.vehicles.index')
            : route('user.vehicles.show', $vehicleId).'#vistorias';
    }
}

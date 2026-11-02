<?php

namespace App\Notifications;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * O proprietário do veículo contestou uma OS que esta oficina registrou
 * (MaintenanceOwnerDecisionService::dispute). Vai para a conta da oficina porque ela é a única que
 * pode corrigir ou excluir o registro — sem este aviso a contestação ficaria invisível para quem
 * tem o poder de resolvê-la.
 *
 * O texto tem de dizer o que a contestação **faz**: ela marca, não esconde (ver
 * `.ai/rules/public-lookup.md`, "OS contestada aparece marcada, não sai"). Este e-mail já afirmou o
 * contrário — que a OS saía da consulta pública — e isso é pior do que impreciso: a oficina autora
 * pode excluir a OS dela mesmo depois do selo (MaintenancePolicy::canChange), então dizer que o
 * registro foi suprimido e emendar com "corrija ou exclua" empurra quem tem NF-e e serviço feito a
 * apagar um registro legítimo — exatamente a remoção unilateral que o fluxo recusa dar ao
 * proprietário. Por isso a mensagem separa os dois casos: engano de digitação se corrige, serviço
 * que aconteceu não se toca.
 */
class MaintenanceDisputedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'maintenance_disputed';

    public function __construct(
        public Maintenance $maintenance,
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
            ->line('A OS continua na consulta pública e no histórico do chassi, agora com a ressalva "Contestada pelo proprietário", e o Selo da oficina não é revogado. O que muda é a quilometragem: ela deixa de contar para o hodômetro do veículo enquanto a contestação estiver aberta.')
            ->line('Se foi engano de digitação (chassi ou placa de outro veículo), corrija ou exclua a OS. Se o serviço foi feito mesmo, não altere nada: guarde a nota fiscal e a ordem de serviço, porque é com elas que a nossa equipe analisa o caso.')
            ->action('Abrir a OS', route('workshop.maintenances.show', $this->maintenance))
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
            'reason' => $this->reason,
        ];
    }

    public function title(): string
    {
        return 'Um proprietário contestou uma OS sua';
    }

    public function body(): string
    {
        $vehicle = $this->maintenance->vehicle;
        $label = $vehicle === null
            ? 'um veículo'
            : trim($vehicle->brand.' '.$vehicle->model);

        return 'O proprietário de '.($label !== '' ? $label : 'um veículo')
            .' diz que o serviço "'.$this->maintenance->maintenance_type.'" de '
            .($this->maintenance->maintenance_date?->format('d/m/Y') ?? 'data não informada')
            .' não aconteceu no carro dele.';
    }
}

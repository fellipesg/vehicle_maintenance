<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\VehicleConsignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa a loja que o proprietário do carro consignado excluiu a conta.
 *
 * A consignação continua ativa — o carro segue no pátio da loja e o histórico que ela registra é
 * o que valoriza o veículo na venda. O que muda é que não há mais ninguém do outro lado: os avisos
 * de cada serviço deixam de chegar e o histórico anterior, se ainda estava fechado, não tem mais
 * quem libere. Daí o aviso.
 *
 * Não repete nome, e-mail nem telefone do proprietário: eles acabaram de ser apagados.
 */
class ConsignmentOwnerAccountDeletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VehicleConsignment $consignment)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vehicle = $this->consignment->vehicle;

        $message = (new MailMessage)
            ->subject("O proprietário do {$this->vehicleLabel()} excluiu a conta")
            ->greeting('Olá!')
            ->line("O proprietário do **{$this->vehicleLabel()}** (placa {$vehicle->license_plate}) excluiu a conta dele no RevisaLog e os dados pessoais foram apagados.")
            ->line('**A consignação continua ativa.** O veículo segue no seu estoque e você continua registrando as manutenções normalmente — esse histórico fica no chassi e acompanha o carro na venda.')
            ->line('O que muda: os avisos de cada serviço não chegam mais a ninguém, e o contato do proprietário saiu da plataforma. Fale com ele pelos seus próprios registros.');

        if ($this->consignment->isHistoryReviewPending()) {
            $message->line('Atenção: o pedido de acesso ao histórico anterior estava aguardando resposta do proprietário e não tem mais quem o responda. Nossa equipe segue podendo analisar a procuração.');
        }

        return $message
            ->action('Ver o veículo', $this->actionUrl())
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $vehicle = $this->consignment->vehicle;

        return [
            'type' => 'consignment-owner-account-deleted',
            'title' => 'Proprietário excluiu a conta',
            'body' => "A consignação do {$this->vehicleLabel()} continua ativa, mas o proprietário saiu da plataforma.",
            'consignment_id' => $this->consignment->id,
            'vehicle_id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
            'action_url' => $this->actionUrl(absolute: false),
        ];
    }

    private function vehicleLabel(): string
    {
        $vehicle = $this->consignment->vehicle;

        return trim("{$vehicle->brand} {$vehicle->model}");
    }

    private function actionUrl(bool $absolute = true): string
    {
        return route('garage.vehicles.show', $this->consignment->vehicle_id, absolute: $absolute);
    }
}

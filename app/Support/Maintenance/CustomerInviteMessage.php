<?php

namespace App\Support\Maintenance;

use App\Models\Maintenance;
use App\Models\MaintenanceInvite;

/**
 * Texto do convite que a oficina manda ao cliente de um carro sem proprietário: o mesmo no WhatsApp
 * e no e-mail. Cita a oficina, marca/modelo/ano e a data do serviço, com o link /convite/{token}.
 * Nunca leva placa, chassi, RENAVAM, quilometragem nem valores (LGPD: placa e chassi identificam a pessoa).
 */
class CustomerInviteMessage
{
    public function __construct(
        private readonly Maintenance $maintenance,
        private readonly MaintenanceInvite $invite,
    ) {}

    public function workshopName(): string
    {
        $maintenance = $this->maintenance;

        return (string) ($maintenance->verifiedWorkshop?->name ?? $maintenance->workshop?->name ?? $maintenance->workshop_name ?? 'Uma oficina');
    }

    public function vehicleLabel(): string
    {
        $vehicle = $this->maintenance->vehicle;

        return trim(implode(' ', array_filter([$vehicle?->brand, $vehicle?->model, $vehicle?->year])));
    }

    public function serviceDate(): string
    {
        return (string) $this->maintenance->maintenance_date?->format('d/m/Y');
    }

    public function inviteUrl(): string
    {
        return route('invites.show', $this->invite->token);
    }

    public function text(): string
    {
        return 'Olá! A oficina '.$this->workshopName().' registrou um serviço no seu '.$this->vehicleLabel()
            .' em '.$this->serviceDate().' no RevisaLog. '
            .'Crie sua conta grátis para ver e guardar o histórico do carro: '.$this->inviteUrl();
    }

    /**
     * Telefone digitado pela oficina em número de WhatsApp (só dígitos, com 55), ou null quando não
     * parece um telefone brasileiro: DDD + 8 ou 9 dígitos, com ou sem 0 e 55 na frente.
     */
    public static function whatsappNumber(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $digits = ltrim($digits, '0');

        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $digits = substr($digits, 2);
        }

        if (! preg_match('/^[1-9]{2}[2-9]\d{7,8}$/', $digits)) {
            return null;
        }

        return '55'.$digits;
    }

    public function whatsappUrl(string $number): string
    {
        return 'https://wa.me/'.$number.'?text='.rawurlencode($this->text());
    }
}

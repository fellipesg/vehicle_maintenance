<?php

namespace App\Enums;

enum WorkshopMessageTrigger: string
{
    case CorrectiveFollowUp = 'corrective_follow_up';
    case ScheduledRevision = 'scheduled_revision';
    case WarrantyExpiring = 'warranty_expiring';
    case Reactivation = 'reactivation';

    public function label(): string
    {
        return match ($this) {
            self::ScheduledRevision => 'Revisão programada',
            self::CorrectiveFollowUp => 'Retorno após serviço',
            self::WarrantyExpiring => 'Garantia vencendo',
            self::Reactivation => 'Cliente sumido',
        };
    }

    /**
     * Quando a mensagem sai, no texto que a oficina lê ao escolher o gatilho.
     */
    public function description(): string
    {
        return match ($this) {
            self::ScheduledRevision => 'Quando o veículo estiver perto da próxima revisão pela quilometragem estimada.',
            self::CorrectiveFollowUp => 'Alguns dias depois de um serviço, para saber como o carro está.',
            self::WarrantyExpiring => 'Antes de vencer uma garantia emitida pela oficina, para uma checagem.',
            self::Reactivation => 'Quando o cliente passa muito tempo sem voltar à oficina.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}

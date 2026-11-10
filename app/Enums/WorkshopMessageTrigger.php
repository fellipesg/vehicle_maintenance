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
     * O gatilho tem rodada no WorkshopMessageTemplateDispatcher.
     *
     * Os outros dois existem no enum e nos planos do config, mas nada os dispara. Enquanto isso,
     * eles não são oferecidos nem aceitos num modelo: um modelo ativo com gatilho sem rodada não
     * manda mensagem nenhuma e, no `match` do dispatcher, levava a rodada diária de todas as
     * oficinas com um UnhandledMatchError. Implementar um deles é acrescentar a rodada e trocar
     * o false por true aqui.
     *
     * Trocar para true não libera o gatilho para todo mundo: em cima disto vem o recorte por plano
     * (App\Support\Workshop\WorkshopPlanLimits::triggers, de
     * config('workshop_messages.plans.*.triggers')), que é quem decide, por exemplo, que
     * `warranty_expiring` é do Pro. Daí o select do formulário sair de
     * WorkshopPlanLimits::triggerOptions() e não de uma lista deste enum.
     */
    public function isDispatchable(): bool
    {
        return match ($this) {
            self::ScheduledRevision, self::CorrectiveFollowUp => true,
            self::WarrantyExpiring, self::Reactivation => false,
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

    /**
     * @return list<self>
     */
    public static function dispatchableCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case): bool => $case->isDispatchable()));
    }

    /**
     * @return list<string>
     */
    public static function dispatchableValues(): array
    {
        return array_map(fn (self $case): string => $case->value, self::dispatchableCases());
    }
}

<?php

namespace App\Enums;

/**
 * Plano da oficina (workshops.plan). Os limites de cada plano ficam em
 * config('workshop_messages.plans') e são lidos por App\Support\Workshop\WorkshopPlanLimits.
 *
 * Do config, `active_templates` (nas quatro rotas que criam ou ativam um modelo de mensagem),
 * `send_window_days` e `min_days_between_messages_per_user` (no WorkshopMessageTemplateDispatcher)
 * são impostos. A lista `triggers` de cada plano não tem quem a leia — o gatilho hoje é limitado
 * por WorkshopMessageTrigger::isDispatchable(), igual para todo plano —, nem
 * `warranty_lead_days_default`, `reactivation_days_default` e `return_window_days`: não as cite
 * como controle sem implementá-las.
 */
enum WorkshopPlan: string
{
    case Free = 'free';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratuito',
            self::Pro => 'Pro',
        };
    }
}

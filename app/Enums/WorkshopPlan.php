<?php

namespace App\Enums;

/**
 * Plano da oficina (workshops.plan). Os limites de cada plano ficam em
 * config('workshop_messages.plans') e são lidos por App\Support\Workshop\WorkshopPlanLimits.
 *
 * Do config são impostos `active_templates` e `triggers` (nas quatro rotas que criam ou alteram um
 * modelo de mensagem; `triggers` também na rodada do WorkshopMessageTemplateDispatcher, porque o
 * plano é trocado pelo admin e o modelo fica no banco), `send_window_days` e
 * `min_days_between_messages_per_user`. A lista `triggers` só **estreita** o que
 * WorkshopMessageTrigger::isDispatchable() já permite: ela não liga gatilho sem rodada, e por isso
 * `warranty_expiring` no Pro ainda não vale nada. Sem leitor: `warranty_lead_days_default`,
 * `reactivation_days_default` e `return_window_days` — não as cite como controle sem implementá-las.
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

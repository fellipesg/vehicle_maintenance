<?php

namespace App\Enums;

/**
 * Plano da oficina (workshops.plan). Os limites de cada plano ficam em
 * config('workshop_messages.plans') e são lidos por App\Support\Workshop\WorkshopPlanLimits.
 *
 * Só `active_templates` é imposto hoje (nas quatro rotas que criam ou ativam um modelo de
 * mensagem). A lista `triggers` de cada plano e as outras chaves do config não têm quem as leia:
 * não as cite como controle sem implementá-las.
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

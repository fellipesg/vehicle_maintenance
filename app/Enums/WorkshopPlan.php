<?php

namespace App\Enums;

/**
 * Plano da oficina (workshops.plan). Os limites de cada plano ficam em
 * config('workshop_messages.plans') e são lidos por App\Support\Workshop\WorkshopPlanLimits.
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

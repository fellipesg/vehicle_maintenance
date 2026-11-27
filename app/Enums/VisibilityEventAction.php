<?php

namespace App\Enums;

/**
 * O que aconteceu num evento de visibilidade: a equipe tirou o registro de circulação, ou devolveu.
 *
 * Vocabulário **compartilhado** pelas duas trilhas (`inspection_visibility_events` e
 * `maintenance_visibility_events`), porque a decisão é a mesma nos dois lados e os serviços são
 * irmãos de propósito (App\Services\Inspection\InspectionPublicVisibilityService e
 * App\Services\Maintenance\MaintenancePublicVisibilityService). Dois enums paralelos deixariam um
 * lado ganhar um estado que o outro não tem, que é exatamente a assimetria que as regras pedem para
 * não existir (.ai/rules/inspections.md, .ai/rules/models.md).
 */
enum VisibilityEventAction: string
{
    case Hidden = 'hidden';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Hidden => 'Suprimido',
            self::Restored => 'Devolvido ao histórico',
        };
    }
}

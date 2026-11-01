<?php

namespace App\Support\Workshop;

use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;

/**
 * Limites do plano da oficina, de config('workshop_messages.plans').
 *
 * Hoje só o teto de modelos de mensagem ativos. Plano sem entrada no config, ou com
 * active_templates null, fica sem teto.
 */
final class WorkshopPlanLimits
{
    public static function activeTemplates(Workshop $workshop): ?int
    {
        $limit = config('workshop_messages.plans.'.$workshop->planOrDefault()->value.'.active_templates');

        return is_int($limit) ? $limit : null;
    }

    /**
     * Cabe mais um modelo ativo? $ignoring é o modelo que está sendo editado: o estado atual dele
     * não conta contra o próprio pedido.
     */
    public static function hasRoomForActiveTemplate(Workshop $workshop, ?WorkshopMessageTemplate $ignoring = null): bool
    {
        $limit = self::activeTemplates($workshop);

        if ($limit === null) {
            return true;
        }

        $active = $workshop->messageTemplates()
            ->where('is_active', true)
            ->when($ignoring?->exists, fn ($query) => $query->whereKeyNot($ignoring->getKey()))
            ->count();

        return $active < $limit;
    }

    public static function activeTemplateLimitMessage(Workshop $workshop): string
    {
        $limit = self::activeTemplates($workshop);
        $plan = $workshop->planOrDefault()->label();

        return "O plano {$plan} permite {$limit} ".($limit === 1 ? 'modelo' : 'modelos')
            .' de mensagem ativos ao mesmo tempo. Desative outro modelo antes de ativar este.';
    }
}

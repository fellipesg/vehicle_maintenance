<?php

namespace App\Support\Workshop;

use App\Enums\WorkshopMessageTrigger;
use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;

/**
 * Limites do plano da oficina, de config('workshop_messages.plans').
 *
 * Dois: o teto de modelos de mensagem ativos e quais gatilhos o plano libera. Plano sem entrada no
 * config, com active_templates null ou sem a chave triggers, fica sem o limite correspondente.
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

    /**
     * Gatilhos que o plano libera, de config('workshop_messages.plans.*.triggers').
     *
     * A lista do plano só **estreita**: ela é cruzada com WorkshopMessageTrigger::dispatchableCases(),
     * porque quem decide se um gatilho pode ser escolhido é a existência da rodada no dispatcher
     * (isDispatchable), não o plano. O config lista `warranty_expiring` e `reactivation` no Pro e
     * nenhum dos dois tem rodada: deixar o plano ampliar criaria modelo ativo que não manda nada e,
     * no `match` do dispatcher, derrubava a rodada diária de *todas* as oficinas.
     *
     * Plano sem a chave, ou com algo que não é lista, fica sem recorte — mesma convenção de
     * active_templates null.
     *
     * @return list<WorkshopMessageTrigger>
     */
    public static function triggers(Workshop $workshop): array
    {
        $allowed = config('workshop_messages.plans.'.$workshop->planOrDefault()->value.'.triggers');
        $dispatchable = WorkshopMessageTrigger::dispatchableCases();

        if (! is_array($allowed)) {
            return $dispatchable;
        }

        return array_values(array_filter(
            $dispatchable,
            fn (WorkshopMessageTrigger $trigger): bool => in_array($trigger->value, $allowed, true),
        ));
    }

    public static function allowsTrigger(Workshop $workshop, WorkshopMessageTrigger $trigger): bool
    {
        return in_array($trigger, self::triggers($workshop), true);
    }

    /**
     * Opções do select do formulário: os gatilhos do plano, mais o do modelo que está sendo editado.
     *
     * O gatilho atual entra mesmo fora do plano porque quem troca o plano é o admin: sem ele, o
     * edit de um modelo herdado de um plano melhor abriria com o select em branco e salvar trocaria
     * o gatilho sem a oficina pedir. Gatilho sem rodada fica fora mesmo sendo o atual — aí o select
     * em branco é o certo, porque esse modelo não dispara nada e salvar tem de corrigi-lo.
     *
     * @return array<string, string>
     */
    public static function triggerOptions(Workshop $workshop, ?WorkshopMessageTemplate $including = null): array
    {
        $options = [];

        foreach (self::triggers($workshop) as $trigger) {
            $options[$trigger->value] = $trigger->label();
        }

        $current = $including?->trigger;

        if ($current instanceof WorkshopMessageTrigger
            && $current->isDispatchable()
            && ! array_key_exists($current->value, $options)) {
            $options[$current->value] = $current->label();
        }

        return $options;
    }

    public static function triggerPlanMessage(Workshop $workshop, WorkshopMessageTrigger $trigger): string
    {
        $plan = $workshop->planOrDefault()->label();

        return "O plano {$plan} não inclui o gatilho \"{$trigger->label()}\". Escolha outro gatilho.";
    }
}

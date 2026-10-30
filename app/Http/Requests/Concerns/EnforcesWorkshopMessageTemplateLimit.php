<?php

namespace App\Http\Requests\Concerns;

use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;
use App\Support\Workshop\WorkshopPlanLimits;
use Illuminate\Validation\Validator;

/**
 * Teto de modelos de mensagem ativos do plano (App\Support\Workshop\WorkshopPlanLimits), nas quatro
 * superfícies que criam ou ativam um modelo: a web e a API, no store e no update. O teto vale no
 * pedido que deixa o modelo ativo; criar ou editar modelo inativo passa sempre.
 */
trait EnforcesWorkshopMessageTemplateLimit
{
    protected function addActiveMessageTemplateLimitRule(
        Validator $validator,
        Workshop $workshop,
        ?WorkshopMessageTemplate $template = null,
    ): void {
        $validator->after(function (Validator $validator) use ($workshop, $template): void {
            // Sem a chave, o store cria ativo (padrão da coluna) e o update mantém o estado atual.
            if (! $this->boolean('is_active', $template?->is_active ?? true)) {
                return;
            }

            if (WorkshopPlanLimits::hasRoomForActiveTemplate($workshop, $template)) {
                return;
            }

            $validator->errors()->add('is_active', WorkshopPlanLimits::activeTemplateLimitMessage($workshop));
        });
    }
}

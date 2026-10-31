<?php

namespace App\Http\Requests\Concerns;

use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;
use App\Support\Workshop\WorkshopPlanLimits;
use Illuminate\Validation\Validator;

/**
 * Teto de modelos de mensagem ativos do plano (App\Support\Workshop\WorkshopPlanLimits), nas quatro
 * superfícies que criam ou ativam um modelo: a web e a API, no store e no update. O teto vale na
 * *transição* para ativo — criar ativo, ou ligar um modelo que estava desligado. Editar um modelo
 * que já estava ativo, e continua, passa sempre, porque não acrescenta nenhum ativo.
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

            // Modelo que já estava ativo e continua ativo não acrescenta nada ao número de ativos.
            // Uma oficina pode estar acima do teto sem ter burlado nada — o teto é novo e o plano é
            // trocado pelo admin —, e nesse estado contar os outros ativos barrava qualquer edição
            // dos modelos que ela já tem, inclusive na única tela onde se desativa um deles.
            if (($template?->exists ?? false) && $template->is_active) {
                return;
            }

            if (WorkshopPlanLimits::hasRoomForActiveTemplate($workshop, $template)) {
                return;
            }

            $validator->errors()->add('is_active', WorkshopPlanLimits::activeTemplateLimitMessage($workshop));
        });
    }
}

<?php

namespace App\Http\Requests\Concerns;

use App\Enums\WorkshopMessageTrigger;
use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;
use App\Support\Workshop\WorkshopPlanLimits;
use Illuminate\Validation\Validator;

/**
 * Limites de plano sobre os modelos de mensagem (App\Support\Workshop\WorkshopPlanLimits), nas
 * quatro superfícies que criam ou alteram um modelo: a web e a API, no store e no update.
 *
 * São dois limites e os dois valem na *transição*, nunca no estado. O teto de ativos vale ao criar
 * ativo ou ligar um modelo desligado; o recorte de gatilhos vale ao escolher um gatilho diferente
 * do que o modelo já tem. Editar um modelo como ele está passa sempre, porque o plano é trocado
 * pelo admin: uma oficina pode ter herdado um ativo a mais ou um gatilho melhor sem burlar nada, e
 * trancá-la trancaria a tela onde ela desativa ou corrige o que tem.
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

    /**
     * Recorte de gatilhos do plano (config('workshop_messages.plans.*.triggers')), em cima da regra
     * do campo, que já recusa gatilho sem rodada.
     */
    protected function addMessageTemplateTriggerPlanRule(
        Validator $validator,
        Workshop $workshop,
        ?WorkshopMessageTemplate $template = null,
    ): void {
        $validator->after(function (Validator $validator) use ($workshop, $template): void {
            $raw = $this->input('trigger');

            // Só string interessa: o gatilho é enum de string, e qualquer outro tipo a regra do
            // campo já recusa. A guarda não é zelo: castar o valor para string fazia `trigger`
            // vindo como array emitir "Array to string conversion", que o HandleExceptions lança
            // como ErrorException — o 422 da validação virava 500 nas quatro rotas.
            if (! is_string($raw)) {
                return;
            }

            $trigger = WorkshopMessageTrigger::tryFrom($raw);

            // Gatilho ausente (o update da API aceita payload parcial) ou fora do enum: a regra do
            // campo já cuida, e aqui não há plano a conferir.
            if ($trigger === null) {
                return;
            }

            // O gatilho que o modelo já tem passa mesmo fora do plano: é o estado, não a transição.
            if (($template?->exists ?? false) && $trigger === $template->trigger) {
                return;
            }

            if (WorkshopPlanLimits::allowsTrigger($workshop, $trigger)) {
                return;
            }

            $validator->errors()->add('trigger', WorkshopPlanLimits::triggerPlanMessage($workshop, $trigger));
        });
    }
}

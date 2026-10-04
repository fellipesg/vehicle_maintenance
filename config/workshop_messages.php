<?php

return [

    /*
    | Intervalo mínimo entre duas mensagens de oficina para o mesmo cliente, somando todas as
    | oficinas. Evita que o cliente receba várias mensagens na mesma semana.
    */
    'min_days_between_messages_per_user' => (int) env('WORKSHOP_MESSAGES_MIN_DAYS_PER_USER', 7),

    /*
    | Janela de envio dos gatilhos por data: um serviço só gera "Retorno após serviço" entre
    | min_days_since_service e min_days_since_service + esta janela. Sem ela, uma oficina nova
    | dispararia mensagens para todo o histórico antigo no primeiro dia.
    */
    'send_window_days' => (int) env('WORKSHOP_MESSAGES_SEND_WINDOW_DAYS', 60),

    /*
    | Garantia vencendo: dias de antecedência quando o modelo não define. O modelo guarda o
    | número de dias em min_days_since_service, que cada gatilho por data lê do seu jeito.
    */
    'warranty_lead_days_default' => 30,

    /*
    | Cliente sumido: dias sem nenhuma OS confirmada na oficina.
    */
    'reactivation_days_default' => 365,

    /*
    | Uma mensagem conta como "retorno" quando o mesmo veículo tem uma nova OS com o Selo da
    | oficina em até este número de dias depois do envio.
    */
    'return_window_days' => 60,

    /*
    | Limites por plano (App\Enums\WorkshopPlan). active_templates nulo = sem limite.
    */
    'plans' => [
        'free' => [
            'active_templates' => 2,
            'triggers' => ['scheduled_revision', 'corrective_follow_up'],
        ],
        'pro' => [
            'active_templates' => null,
            'triggers' => ['scheduled_revision', 'corrective_follow_up', 'warranty_expiring', 'reactivation'],
        ],
    ],

];

{{--
    A trilha de supressão de um laudo ou de uma OS: uma linha por supressão e por devolução, da mais
    antiga para a mais nova, com ator, motivo e horário.

    Existe porque as colunas de estado são um interruptor — devolver o registro zera
    `hidden_by_user_id` e `hidden_reason` —, então sem a trilha ninguém descobriria que a supressão
    existiu. Supressão sem auditoria visível não serve para nada, e esta é a superfície que a mostra:
    o admin consulta direto, sem passar pela redação (.ai/rules/admin.md).

    **Só no admin.** Nada disto vai para superfície pública nem para a API: o que o comprador vê é o
    registro ou a ausência dele, não o nome de quem da equipe decidiu
    (.ai/rules/inspections.md, .ai/rules/public-lookup.md).

    Um só componente para as duas telas de propósito: os dois serviços são irmãos e a equipe lê um
    esperando o outro. Os dois modelos de evento respondem à mesma interface (action, actor, reason,
    occurred_at), então a view não sabe de qual tabela a linha veio.

    Props:
    - events (obrigatório): a coleção de App\Models\InspectionVisibilityEvent ou
      App\Models\MaintenanceVisibilityEvent, já carregada com `actor` (preventLazyLoading está
      ligado fora de produção).
    - label (obrigatório): o que a trilha descreve, para o leitor de tela ("do laudo LDO-1234-AB").

    Ex.: <x-admin.visibility-trail :events="$inspection->visibilityEvents" :label="'do laudo '.$inspection->verification_code" />
--}}
@props([
    'events' => null,
    'label' => '',
])

@php
    $trail = $events ?? collect();
@endphp

@if($trail->isNotEmpty())
    <div class="mt-2 border-t border-border pt-2" data-visibility-trail="{{ $trail->count() }}">
        <p class="text-xs font-medium text-muted-foreground">
            Histórico de supressão <span class="sr-only">{{ $label }}</span>
        </p>

        <ol class="mt-1 space-y-1">
            @foreach($trail as $event)
                <li class="text-xs text-muted-foreground" data-visibility-event="{{ $event->action->value }}">
                    <span class="font-medium text-foreground">{{ $event->action->label() }}</span>
                    @if($event->actor) · {{ $event->actor->name }}@endif
                    @if($event->occurred_at) · {{ $event->occurred_at->format('d/m/Y H:i') }}@endif
                    @if(filled($event->reason))
                        <span class="block">{{ $event->reason }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
@endif

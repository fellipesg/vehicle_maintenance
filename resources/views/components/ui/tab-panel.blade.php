{{--
    Painel de <x-ui.tabs> (vai no slot padrão).

    Props:
    - id (obrigatório): o mesmo target da <x-ui.tab> correspondente.
    - active: painel visível ao carregar. Os outros saem com o atributo hidden.

    O painel entra no Tab (tabindex="0") para quem usa teclado chegar ao conteúdo mesmo quando ele
    não tem nenhum controle. A troca de painel tem um fade curto só sem prefers-reduced-motion.

    Ex.: <x-ui.tab-panel id="placas">...</x-ui.tab-panel>
--}}
@props([
    'id',
    'active' => false,
])
<div
    id="{{ $id }}"
    role="tabpanel"
    aria-labelledby="{{ $id }}-aba"
    tabindex="0"
    data-ui-tab-panel
    @unless($active) hidden @endunless
    {{ $attributes->class('mt-4 rounded-control motion-safe:transition-opacity motion-safe:duration-fast motion-safe:ease-smooth-out motion-safe:starting:opacity-0 motion-reduce:transition-none') }}
>
    {{ $slot }}
</div>

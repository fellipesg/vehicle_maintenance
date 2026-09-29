{{--
    Aba de <x-ui.tabs> (vai no slot tabs). O visual segue a variant do <x-ui.tabs>.

    Props:
    - target (obrigatório): id do <x-ui.tab-panel> que a aba mostra. A aba ganha o id
      "{target}-aba", que o painel usa em aria-labelledby.
    - active: aba aberta ao carregar (marque também o painel).
    - icon: nome de <x-ui.icon> antes do texto.
    - badge: contador depois do texto (ex.: 3); badgeLabel dá o texto para leitor de tela
      ("não lidas").
    - disabled: aba visível mas inativa.

    A aba ativa se distingue por forma, não só por cor: fundo e sombra (pills) ou barra inferior
    (line), além do texto em negrito.

    Ex.: <x-ui.tab target="placas" badge="2" badge-label="placas antigas">Histórico de placas</x-ui.tab>
--}}
@aware(['variant' => 'pills'])
@props([
    'target',
    'active' => false,
    'icon' => null,
    'badge' => null,
    'badgeLabel' => null,
    'disabled' => false,
])
@php
    // @aware procura "variant" em todos os componentes acima: valor que não é de abas cai no pills.
    $tabVariantClass = match (in_array($variant, ['pills', 'line'], true) ? $variant : 'pills') {
        'pills' => 'min-h-10 rounded-md px-3 aria-selected:bg-surface aria-selected:shadow-sm',
        'line' => '-mb-px min-h-11 border-b-2 border-transparent px-1 hover:border-border-strong aria-selected:border-link aria-selected:hover:border-link',
    };
    $tabIsActive = (bool) $active && ! $disabled;
@endphp
<button
    type="button"
    role="tab"
    id="{{ $target }}-aba"
    aria-controls="{{ $target }}"
    aria-selected="{{ $tabIsActive ? 'true' : 'false' }}"
    tabindex="{{ $tabIsActive ? '0' : '-1' }}"
    data-ui-tab="{{ $target }}"
    @disabled($disabled)
    {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap text-sm font-medium text-muted-foreground transition-[color,background-color,border-color,box-shadow] duration-fast hover:text-foreground focus-visible:outline-offset-[-2px] disabled:pointer-events-none disabled:opacity-50 aria-selected:font-semibold aria-selected:text-foreground motion-reduce:transition-none',
        $tabVariantClass,
    ]) }}
>
    @if($icon)<x-ui.icon :name="$icon" class="size-4" />@endif
    <span>{{ $slot }}</span>
    @if(filled($badge))
        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-border px-1.5 text-xs font-semibold text-foreground">{{ $badge }}@if(filled($badgeLabel))<span class="sr-only"> {{ $badgeLabel }}</span>@endif</span>
    @endif
</button>

{{--
    Item de <x-ui.nav>: <li> com o link. O visual segue a variant do <x-ui.nav> (@aware); ao escrever
    os itens no slot, passe variant ao <x-ui.nav> mesmo quando for a horizontal.

    Props:
    - href (obrigatório) e o slot (rótulo).
    - active: página atual. Ganha aria-current="page", texto em negrito e um indicador de forma:
      barra inferior de 2px (horizontal) ou barra lateral com fundo de destaque (vertical).
    - icon: nome de <x-ui.icon> antes do rótulo.
    - badge: contador (ex.: não lidas). badgeLabel completa o número para leitor de tela.
    - itemClass: classes do <li> (ex.: max-md:hidden para esconder numa largura).
    Atributos extras (data-*, class) vão para o <a>.

    Ex.: <x-ui.nav-item :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" icon="bell" :badge="$unread" badge-label="não lidas">Notificações</x-ui.nav-item>
--}}
@props([
    'href',
    'active' => false,
    'icon' => null,
    'badge' => null,
    'badgeLabel' => null,
    'itemClass' => null,
    'variant' => null,
])
@aware(['variant' => 'horizontal'])
@php
    // @aware procura "variant" em todos os componentes acima, não só no <x-ui.nav>: um valor que não
    // é de nav (ex.: variant de um card em volta) cai no horizontal em vez de quebrar a página.
    $navItemVariant = in_array($variant, ['horizontal', 'vertical'], true) ? $variant : 'horizontal';
    $navItemIsActive = (bool) $active;
    $navItemClasses = match ($navItemVariant) {
        'horizontal' => [
            'relative inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-control px-3 text-sm transition-colors duration-fast motion-reduce:transition-none',
            'text-muted-foreground hover:bg-surface-muted hover:text-foreground' => ! $navItemIsActive,
            'font-semibold text-foreground after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:rounded-full after:bg-accent-foreground' => $navItemIsActive,
        ],
        'vertical' => [
            'relative flex min-h-11 items-center gap-3 rounded-control px-3 text-sm transition-colors duration-fast motion-reduce:transition-none',
            'text-muted-foreground hover:bg-surface-muted hover:text-foreground' => ! $navItemIsActive,
            'bg-accent font-semibold text-accent-foreground before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-accent-foreground' => $navItemIsActive,
        ],
    };
    $navItemIsVertical = $navItemVariant === 'vertical';
@endphp
<li @if(filled($itemClass)) class="{{ $itemClass }}" @endif>
    <a
        href="{{ $href }}"
        @if($navItemIsActive) aria-current="page" @endif
        {{ $attributes->class($navItemClasses) }}
    >
        @if($icon)<x-ui.icon :name="$icon" class="size-5" />@endif
        <span @class(['min-w-0 flex-1 truncate' => $navItemIsVertical])>{{ $slot }}</span>
        @if(filled($badge))
            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-bold text-primary-foreground">{{ $badge }}@if(filled($badgeLabel))<span class="sr-only"> {{ $badgeLabel }}</span>@endif</span>
        @endif
    </a>
</li>

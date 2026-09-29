{{--
    Item de <x-ui.dropdown>. Vira link, botão, formulário ou separador conforme as props:
    - href: link (<a role="menuitem">).
    - action (+ method, padrão POST): formulário com @csrf e botão de envio, para ações como "Sair".
      PUT, PATCH e DELETE entram por @method. data-confirm e afins vão para o botão de envio
      (resources/js/ui/confirm.js confirma antes de enviar).
    - sem href nem action: <button type="button" role="menuitem">, para ações em JavaScript
      (ex.: data-dialog-open="id").
    - separator: linha divisória (role="separator"); não usa o slot.
    - heading: rótulo de grupo ou cabeçalho do menu (nome e e-mail da conta), não interativo.

    Outras props:
    - icon: nome de <x-ui.icon> à esquerda do texto.
    - variant: default | danger (texto vermelho, para excluir).
    - disabled: item visível mas inativo (aria-disabled; o link perde o href).

    Atributos extras (class, data-*, target) vão para o elemento interativo.

    Ex.:
    <x-ui.dropdown-item heading>{{ $user->email }}</x-ui.dropdown-item>
    <x-ui.dropdown-item :href="route('notifications.index')" icon="bell">Notificações</x-ui.dropdown-item>
    <x-ui.dropdown-item separator />
    <x-ui.dropdown-item :action="route('logout')" icon="arrow-right-start-on-rectangle">Sair</x-ui.dropdown-item>
--}}
@props([
    'href' => null,
    'action' => null,
    'method' => 'POST',
    'icon' => null,
    'variant' => 'default',
    'separator' => false,
    'heading' => false,
    'disabled' => false,
])
@php
    $dropdownItemVariantClass = match (\App\Support\UiProps::oneOf('x-ui.dropdown-item', 'variant', $variant, ['default', 'danger'], 'default')) {
        'default' => 'text-foreground hover:bg-surface-muted focus-visible:bg-surface-muted',
        'danger' => 'text-danger hover:bg-danger-soft focus-visible:bg-danger-soft',
    };
    $dropdownItemMethod = \App\Support\UiProps::oneOf('x-ui.dropdown-item', 'method', strtoupper((string) $method), ['POST', 'PUT', 'PATCH', 'DELETE'], 'POST');
    $dropdownItemIsDisabled = (bool) $disabled;
    $dropdownItemClasses = [
        'flex min-h-10 w-full items-center gap-2.5 rounded-control px-3 text-left text-sm transition-colors duration-fast focus-visible:outline-offset-[-2px] motion-reduce:transition-none',
        $dropdownItemVariantClass,
        'pointer-events-none opacity-60' => $dropdownItemIsDisabled,
    ];
@endphp
@if($separator)
    <div role="separator" {{ $attributes->class('-mx-1 my-1 h-px bg-border') }}></div>
@elseif($heading)
    <div role="none" {{ $attributes->class('px-3 py-2 text-xs font-medium text-muted-foreground') }}>{{ $slot }}</div>
@elseif(filled($href) && ! $dropdownItemIsDisabled)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->class($dropdownItemClasses) }}>
        @if($icon)<x-ui.icon :name="$icon" class="size-5 opacity-80" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </a>
@elseif(filled($action))
    <form method="POST" action="{{ $action }}" role="none">
        @csrf
        @if($dropdownItemMethod !== 'POST')
            @method($dropdownItemMethod)
        @endif
        <button type="submit" role="menuitem" @disabled($dropdownItemIsDisabled) {{ $attributes->class($dropdownItemClasses) }}>
            @if($icon)<x-ui.icon :name="$icon" class="size-5 opacity-80" />@endif
            <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
        </button>
    </form>
@else
    <button
        type="button"
        role="menuitem"
        @if($dropdownItemIsDisabled) aria-disabled="true" tabindex="-1" @endif
        {{ $attributes->class($dropdownItemClasses) }}
    >
        @if($icon)<x-ui.icon :name="$icon" class="size-5 opacity-80" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </button>
@endif

{{--
    Menu suspenso com o HSDropdown do Preline: abre por clique, Enter, Espaço ou seta para baixo;
    setas, Home, End e a primeira letra percorrem os itens; Esc e clique fora fecham e o foco volta
    ao gatilho. Os itens são <x-ui.dropdown-item>.

    Props:
    - id: prefixo dos ids do gatilho e do menu (gerado quando omitido).
    - label: aria-label do gatilho. Obrigatório quando o gatilho só tem ícone ou avatar.
    - placement: bottom-end (o padrão) | bottom-start | top-end | top-start. "end" alinha o menu
      pela direita do gatilho.
    - width: sm (w-48) | md (w-56, o padrão) | lg (w-72). Nunca passa da tela menos 2rem.

    Slots:
    - trigger (obrigatório): conteúdo do <button> gatilho, que o componente já renderiza com
      aria-haspopup="menu", aria-expanded e aria-controls. Atributos do slot vão para o botão
      (<x-slot:trigger class="...">); sem class, o gatilho ganha o visual de botão fantasma.
    - o padrão: os itens.

    Ex.:
    <x-ui.dropdown label="Conta de {{ $user->name }}" width="lg">
        <x-slot:trigger class="inline-flex size-10 items-center justify-center rounded-full ...">{{ $initials }}</x-slot:trigger>
        <x-ui.dropdown-item :href="route('account.edit')" icon="user-circle">Minha conta</x-ui.dropdown-item>
        <x-ui.dropdown-item separator />
        <x-ui.dropdown-item :action="route('logout')" icon="arrow-right-start-on-rectangle">Sair</x-ui.dropdown-item>
    </x-ui.dropdown>
--}}
@props([
    'id' => null,
    'label' => null,
    'placement' => 'bottom-end',
    'width' => 'md',
])
@php
    [$dropdownPlacementClass, $dropdownOriginClass] = match (\App\Support\UiProps::oneOf('x-ui.dropdown', 'placement', $placement, ['bottom-end', 'bottom-start', 'top-end', 'top-start'], 'bottom-end')) {
        'bottom-end' => ['[--placement:bottom-right]', 'origin-top-right'],
        'bottom-start' => ['[--placement:bottom-left]', 'origin-top-left'],
        'top-end' => ['[--placement:top-right]', 'origin-bottom-right'],
        'top-start' => ['[--placement:top-left]', 'origin-bottom-left'],
    };
    $dropdownWidthClass = match (\App\Support\UiProps::oneOf('x-ui.dropdown', 'width', $width, ['sm', 'md', 'lg'], 'md')) {
        'sm' => 'w-48',
        'md' => 'w-56',
        'lg' => 'w-72',
    };
    \App\Support\UiProps::required('x-ui.dropdown', 'trigger', $trigger ?? null, 'Use <x-slot:trigger> com o conteúdo do botão.');
    $dropdownId = $id ?? 'menu-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $dropdownTriggerId = $dropdownId.'-gatilho';
    $dropdownMenuId = $dropdownId.'-itens';
    $dropdownTriggerAttributes = isset($trigger) ? $trigger->attributes : new \Illuminate\View\ComponentAttributeBag;
    $dropdownTriggerHasClass = $dropdownTriggerAttributes->has('class');
@endphp
<div {{ $attributes->class(['hs-dropdown relative inline-flex', $dropdownPlacementClass]) }} data-ui-dropdown>
    <button
        type="button"
        id="{{ $dropdownTriggerId }}"
        {{ $dropdownTriggerAttributes->class([
            'hs-dropdown-toggle',
            'inline-flex min-h-10 items-center gap-2 rounded-control px-3 text-sm font-medium text-foreground transition-colors duration-fast hover:bg-surface-muted motion-reduce:transition-none' => ! $dropdownTriggerHasClass,
        ])->merge([
            'aria-haspopup' => 'menu',
            'aria-expanded' => 'false',
            'aria-controls' => $dropdownMenuId,
            'aria-label' => filled($label) ? $label : null,
        ]) }}
    >{{ $trigger ?? '' }}</button>

    <div
        id="{{ $dropdownMenuId }}"
        role="menu"
        aria-labelledby="{{ $dropdownTriggerId }}"
        @class([
            'hs-dropdown-menu theme-default z-40 hidden max-w-[calc(100vw-2rem)] rounded-card border border-border bg-surface p-1 text-sm text-foreground shadow-lg',
            $dropdownWidthClass,
            $dropdownOriginClass,
            'opacity-0 hs-dropdown-open:opacity-100 motion-safe:scale-[.97] motion-safe:hs-dropdown-open:scale-100',
            'transition-[opacity,scale] duration-fast ease-smooth-out motion-reduce:transition-none',
        ])
    >
        {{ $slot }}
    </div>
</div>

{{--
    Botão que recolhe a sidebar do admin para o modo só ícones (a partir de md; abaixo disso a
    sidebar é a gaveta). Botão de alternância com nome fixo, "Recolher menu lateral", e o estado em
    aria-pressed (true = recolhida).

    O estado fica em data-sidebar="collapsed" no <html>. layouts.admin aplica a escolha salva antes
    da primeira pintura (para a sidebar não abrir larga e encolher) e resources/js/ui/sidebar.js
    alterna, grava no localStorage (com try/catch: modo privado e armazenamento bloqueado só perdem
    a lembrança) e mostra a dica com o nome de cada item enquanto a sidebar está recolhida.

    Quem obedece ao estado são as classes md:in-data-[sidebar=collapsed]:* do layout e do
    nav-admin: largura w-16 (200ms, linear), rótulos em sr-only e ícones centralizados.

    Props:
    - target: id da sidebar controlada (o padrão é 'nav-admin').
    - storageKey: chave no localStorage (o padrão é 'revisalog:admin-sidebar').

    Ex.: <x-ui.sidebar-toggle target="nav-admin" class="-ml-2" />
--}}
@props([
    'target' => 'nav-admin',
    'storageKey' => 'revisalog:admin-sidebar',
])
@php
    \App\Support\UiProps::required('x-ui.sidebar-toggle', 'target', $target);
    \App\Support\UiProps::required('x-ui.sidebar-toggle', 'storageKey', $storageKey);
@endphp
<button
    type="button"
    {{ $attributes->class([
        'relative hidden size-10 shrink-0 items-center justify-center rounded-control text-muted-foreground md:inline-flex',
        'transition-[color,background-color] duration-fast ease-smooth-out hover:bg-surface-muted hover:text-foreground motion-reduce:transition-none',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
    ])->merge([
        'aria-label' => 'Recolher menu lateral',
        'aria-pressed' => 'false',
        'aria-controls' => $target,
        'data-sidebar-toggle' => $target,
        'data-sidebar-storage-key' => $storageKey,
    ]) }}
>
    <x-ui.icon name="chevron-left" class="size-5 transition-transform duration-base ease-smooth-out motion-reduce:transition-none in-data-[sidebar=collapsed]:rotate-180" />
</button>

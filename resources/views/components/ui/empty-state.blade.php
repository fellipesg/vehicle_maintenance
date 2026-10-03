{{--
    Estado vazio: ícone, título, uma frase que explica o benefício e a ação principal.

    Props:
    - title: obrigatório ("Nenhum veículo ainda"). Para filtro sem resultado, diga o filtro
      ("Nenhuma manutenção com Selo da oficina") e ofereça "Limpar filtros".
    - description: a frase de apoio ("Cadastre pelo chassi para começar o histórico que acompanha o
      carro."). Também aceita <x-slot:description>.
    - icon: nome de ícone de x-ui.icon, num círculo neutro (decorativo).
    - heading-level: h2 | h3 (o padrão) | h4 | p (sem título de seção, ex.: dentro de tabela).
    - variant: dashed (o padrão, borda tracejada) | plain (sem borda, dentro de card ou tabela).
    - size: sm (compacto, em card e tabela) | md (o padrão).

    Slots: actions (botões, com o primário primeiro) e o padrão (conteúdo extra, abaixo da
    descrição).

    Ex.: <x-ui.empty-state icon="truck" title="Nenhum veículo ainda" description="Cadastre pelo chassi para começar o histórico.">
             <x-slot:actions><x-ui.button icon="plus" :href="route('user.vehicles.create')">Adicionar veículo</x-ui.button></x-slot:actions>
         </x-ui.empty-state>
--}}
@props([
    'title' => null,
    'description' => null,
    'icon' => null,
    'headingLevel' => 'h3',
    'variant' => 'dashed',
    'size' => 'md',
])
@php
    \App\Support\UiProps::required('x-ui.empty-state', 'title', $title);

    $emptyHeadingTag = \App\Support\UiProps::oneOf('x-ui.empty-state', 'heading-level', $headingLevel, ['h2', 'h3', 'h4', 'p'], 'h3');
    $emptyVariant = \App\Support\UiProps::oneOf('x-ui.empty-state', 'variant', $variant, ['dashed', 'plain'], 'dashed');
    $emptySize = \App\Support\UiProps::oneOf('x-ui.empty-state', 'size', $size, ['sm', 'md'], 'md');
    $emptyHasDescription = ! \App\Support\UiProps::isBlank($description);
    $emptyHasActions = isset($actions) && ! \App\Support\UiProps::isBlank($actions);
@endphp
<div {{ $attributes->class([
    'flex flex-col items-center text-center text-balance',
    'rounded-card border border-dashed border-border-strong' => $emptyVariant === 'dashed',
    'gap-3 px-4 py-6' => $emptySize === 'sm',
    'gap-4 px-6 py-10 sm:py-12' => $emptySize === 'md',
])->merge(['data-slot' => 'empty-state']) }}>
    @if(filled($icon))
        <span data-slot="empty-state-icon" @class([
            'inline-flex shrink-0 items-center justify-center rounded-full bg-surface-muted text-muted-foreground',
            'size-10' => $emptySize === 'sm',
            'size-12' => $emptySize === 'md',
        ])>
            <x-ui.icon :name="$icon" :class="$emptySize === 'sm' ? 'size-5' : 'size-6'" />
        </span>
    @endif
    <div class="max-w-md space-y-1">
        <{{ $emptyHeadingTag }} data-slot="empty-state-title" class="text-base leading-6 font-semibold text-foreground">{{ $title }}</{{ $emptyHeadingTag }}>
        @if($emptyHasDescription)
            <p data-slot="empty-state-description" class="text-sm text-muted-foreground">{{ $description }}</p>
        @endif
    </div>
    @if($slot->hasActualContent())
        <div class="max-w-md text-sm text-muted-foreground">{{ $slot }}</div>
    @endif
    @if($emptyHasActions)
        <div data-slot="empty-state-actions" class="flex flex-wrap items-center justify-center gap-2">{{ $actions }}</div>
    @endif
</div>

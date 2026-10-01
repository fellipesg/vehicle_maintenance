{{--
    Esqueleto de carregamento no formato do conteúdo final, para não pular o layout quando o dado
    chega.

    Props:
    - shape: line (o padrão; linhas de texto) | block (retângulo: card, capa, gráfico) | circle
      (avatar).
    - lines: quantas linhas no shape line (a última fica mais curta). Padrão 1.
    - label: texto para leitor de tela ("Carregando veículos…"), num role="status". O padrão é
      "Carregando…". Com label vazio ("") o esqueleto é só decorativo (aria-hidden): use quando
      vários esqueletos formam um bloco e só um deles anuncia.

    Tamanho: pela classe. Sem h-* o block tem h-24; sem size-*/w-*/h-* o circle tem size-10; sem
    w-* line e block ocupam a largura do contêiner.
    O pulso só roda com motion-safe (com prefers-reduced-motion o bloco fica parado).

    Ex.: <x-ui.skeleton lines="3" label="Carregando manutenções…" />
         <x-ui.skeleton shape="block" class="h-48" label="" />
--}}
@props([
    'shape' => 'line',
    'lines' => 1,
    'label' => 'Carregando…',
])
@php
    $skeletonShape = \App\Support\UiProps::oneOf('x-ui.skeleton', 'shape', $shape, ['line', 'block', 'circle'], 'line');
    $skeletonLines = max(1, min(12, (int) $lines));
    $skeletonLabel = filled($label) ? (string) $label : null;
    $skeletonCustomClass = \Illuminate\Support\Arr::toCssClasses(\Illuminate\Support\Arr::wrap($attributes->get('class', [])));
    $skeletonHasHeight = preg_match('/(?:^|\s)!?(?:size|h)-/', $skeletonCustomClass) === 1;
    $skeletonHasWidth = preg_match('/(?:^|\s)!?(?:size|w)-/', $skeletonCustomClass) === 1;
    $skeletonHasSize = preg_match('/(?:^|\s)!?(?:size|w|h)-/', $skeletonCustomClass) === 1;
    $skeletonPulse = 'bg-surface-muted motion-safe:animate-pulse';
@endphp
<div {{ $attributes->except('class')->class([
    'block' => $skeletonShape !== 'circle',
    'w-full' => $skeletonShape !== 'circle' && ! $skeletonHasWidth,
    'h-24' => $skeletonShape === 'block' && ! $skeletonHasHeight,
    'size-10' => $skeletonShape === 'circle' && ! $skeletonHasSize,
    'shrink-0' => $skeletonShape === 'circle',
    $skeletonCustomClass => $skeletonCustomClass !== '',
])->merge([
    'data-slot' => 'skeleton',
    'data-shape' => $skeletonShape,
    'aria-busy' => $skeletonLabel !== null ? 'true' : null,
    'aria-hidden' => $skeletonLabel === null ? 'true' : null,
]) }}>
    @if($skeletonLabel !== null)
        <span role="status" class="sr-only">{{ $skeletonLabel }}</span>
    @endif
    @if($skeletonShape === 'line')
        <div aria-hidden="true" class="space-y-2.5">
            @for($skeletonLine = 1; $skeletonLine <= $skeletonLines; $skeletonLine++)
                <div @class([
                    'h-4 rounded-control',
                    $skeletonPulse,
                    'w-2/3' => $skeletonLines > 1 && $skeletonLine === $skeletonLines,
                ])></div>
            @endfor
        </div>
    @else
        <div aria-hidden="true" @class([
            'size-full',
            $skeletonPulse,
            'rounded-card' => $skeletonShape === 'block',
            'rounded-full' => $skeletonShape === 'circle',
        ])></div>
    @endif
</div>

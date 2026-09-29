{{--
    Indicador de carregamento (círculo girando). A cor vem do texto (currentColor).

    Props:
    - size: sm (16px) | md (20px, o padrão) | lg (32px).
    - label: sem label é decorativo (aria-hidden), para usar dentro de um controle que já anuncia o
      estado (x-ui.button com loading usa aria-busy). Com label ganha role="status" e o texto fica
      só para leitor de tela ("Carregando veículos…").

    Movimento: gira só com motion-safe; com prefers-reduced-motion o ícone fica parado e o texto do
    controle ou do label carrega a informação.

    Ex.: <x-ui.spinner label="Carregando manutenções…" class="text-muted-foreground" />
--}}
@props([
    'size' => 'md',
    'label' => null,
])
@php
    $spinnerSize = \App\Support\UiProps::oneOf('x-ui.spinner', 'size', $size, ['sm', 'md', 'lg'], 'md');
    $spinnerSvgClass = match ($spinnerSize) {
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-8',
    };
    $spinnerLabel = filled($label) ? (string) $label : null;
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center'])->merge([
    'data-slot' => 'spinner',
    'role' => $spinnerLabel !== null ? 'status' : null,
    'aria-hidden' => $spinnerLabel === null ? 'true' : null,
]) }}><svg class="{{ $spinnerSvgClass }} motion-safe:animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path></svg>@if($spinnerLabel !== null)<span class="sr-only">{{ $spinnerLabel }}</span>@endif</span>@php
    // Sem quebra de linha depois do elemento: o fechamento deste bloco PHP engole a do fim do
    // arquivo, e o componente não ganha espaço sobrando quando fica no meio do texto.
@endphp

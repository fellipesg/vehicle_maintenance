{{--
    Ícone Heroicons v2 (MIT, Tailwind Labs) em SVG inline, lido de resources/js/ui/icons.json (a mesma
    fonte do helper icon() de resources/js/ui/icons.js). Cor sempre currentColor.

    Props:
    - name: nome do Heroicons (x-mark, check-circle, wrench-screwdriver...). Nome inexistente lança
      exceção em local e testing; nos outros ambientes é reportado e nada é desenhado.
    - variant: outline (24px, traço 1,5; o padrão) | solid (mini de 20px, para 16px em badge,
      alerta e tabela densa).
    - title: sem title o ícone é decorativo (aria-hidden="true"); com title vira imagem com nome
      (role="img", aria-label e <title>). Ícone que é o único conteúdo de um botão ganha o nome no
      botão (aria-label), não aqui.
    - class: tamanho e cor, em texto ou em array (:class). Sem size-*, w-* ou h-* entra size-5;
      shrink-0 entra sempre.

    Ex.: <x-ui.icon name="check-circle" variant="solid" class="size-4 text-success" />

    O componente não emite espaço antes nem depois do <svg>, para caber dentro de texto corrido.
--}}@props([
    'name',
    'variant' => \App\Support\IconLibrary::DEFAULT_VARIANT,
    'title' => null,
])
@php
    $iconBody = \App\Support\IconLibrary::bodyForView((string) $name, (string) $variant);
    $iconTitle = filled($title) ? (string) $title : null;
    $iconCustomClass = \Illuminate\Support\Arr::toCssClasses(\Illuminate\Support\Arr::wrap($attributes->get('class', [])));
    $iconHasSize = preg_match('/(?:^|\s)!?(?:size|w|h)-/', $iconCustomClass) === 1;
@endphp
@if($iconBody !== null)<svg {{ $attributes->except('class')->class(['size-5' => ! $iconHasSize, 'shrink-0', $iconCustomClass => $iconCustomClass !== ''])->merge(
    ['xmlns' => 'http://www.w3.org/2000/svg']
    + \App\Support\IconLibrary::svgAttributes((string) $variant)
    + ['data-slot' => 'icon', 'focusable' => 'false']
    + ($iconTitle !== null ? ['role' => 'img', 'aria-label' => $iconTitle] : ['aria-hidden' => 'true'])
) }}>@if($iconTitle !== null)<title>{{ $iconTitle }}</title>@endif{!! $iconBody !!}</svg>@endif

{{--
    Largura máxima e recuo lateral padrão da página (px-4 sm:px-6), centralizado.

    Props:
    - size: sm (max-w-2xl, formulário) | md (max-w-3xl, leitura) | lg (max-w-5xl, detalhe) |
      xl (max-w-7xl, listas e Início; o padrão).
    - as: div (o padrão) | main | section | article | header | footer | nav.
    - padded: soma o recuo vertical padrão (py-6 sm:py-8). Sem ele, só largura e laterais. No admin
      o layout já é dono do recuo: as views não usam container.

    Ex.: <x-ui.container size="sm" padded>...formulário...</x-ui.container>
--}}
@props([
    'size' => 'xl',
    'as' => 'div',
    'padded' => false,
])
@php
    $containerSize = \App\Support\UiProps::oneOf('x-ui.container', 'size', $size, ['sm', 'md', 'lg', 'xl'], 'xl');
    $containerTag = \App\Support\UiProps::oneOf('x-ui.container', 'as', $as, ['div', 'main', 'section', 'article', 'header', 'footer', 'nav'], 'div');
    $containerWidthClass = match ($containerSize) {
        'sm' => 'max-w-2xl',
        'md' => 'max-w-3xl',
        'lg' => 'max-w-5xl',
        'xl' => 'max-w-7xl',
    };
@endphp
<{{ $containerTag }} {{ $attributes->class([
    'mx-auto w-full px-4 sm:px-6',
    $containerWidthClass,
    'py-6 sm:py-8' => (bool) $padded,
])->merge(['data-slot' => 'container']) }}>{{ $slot }}</{{ $containerTag }}>

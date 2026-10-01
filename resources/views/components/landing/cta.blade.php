{{--
    CTA da landing: um x-ui.button com seta que avança 3px no hover e no foco e 5px, menor, ao
    pressionar (landing-arrow-link, do interactive-hover-button do ObsidianUI). O texto não é
    duplicado, então o leitor de tela lê o rótulo uma vez, e a seta (aria-hidden) só anda com
    prefers-reduced-motion: no-preference. O hover só vale em aparelho com ponteiro (hover: hover),
    então a seta não fica presa depois de um toque.

    Props:
    - href (obrigatório): destino.
    - variant: primary (o padrão) | secondary.
    - size: lg (o padrão, 44px) | md.

    Ex.: <x-landing.cta :href="route('register')">Começar grátis</x-landing.cta>
--}}
@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'lg',
])
@php
    \App\Support\UiProps::required('x-landing.cta', 'href', $href);
    $ctaVariant = \App\Support\UiProps::oneOf('x-landing.cta', 'variant', $variant, ['primary', 'secondary'], 'primary');
    $ctaSize = \App\Support\UiProps::oneOf('x-landing.cta', 'size', $size, ['md', 'lg'], 'lg');
@endphp
<x-ui.button
    :href="$href"
    :variant="$ctaVariant"
    :size="$ctaSize"
    icon-trailing="arrow-right"
    :attributes="$attributes->class([
        '*:data-[slot=icon]:transition-[translate,scale] *:data-[slot=icon]:duration-base *:data-[slot=icon]:ease-smooth-out motion-reduce:*:data-[slot=icon]:transition-none',
        'motion-safe:hover:*:data-[slot=icon]:translate-x-[3px] motion-safe:focus-visible:*:data-[slot=icon]:translate-x-[3px]',
        'motion-safe:active:*:data-[slot=icon]:translate-x-[5px] motion-safe:active:*:data-[slot=icon]:scale-[.94]',
    ])->merge(['data-landing-cta' => ''])"
>{{ $slot }}</x-ui.button>@php
    // Sem quebra de linha depois do elemento: o fechamento deste bloco PHP engole a do fim do
    // arquivo, e o componente não ganha espaço sobrando quando fica no meio do texto.
@endphp

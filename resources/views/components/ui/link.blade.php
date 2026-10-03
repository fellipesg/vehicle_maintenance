{{--
    Link de texto ou de ação no estilo .link (--color-link: 6,31:1 no branco; wrench-400 dentro de
    .theme-inverse). Para ação com cara de botão, use x-ui.button.

    Props:
    - href: obrigatório.
    - variant: standalone (o padrão: "Ver todos", "Voltar"; sublinha no hover e no foco) | inline
      (dentro de parágrafo: sempre sublinhado, porque link não pode se distinguir só pela cor).
    - icon: ícone antes do texto (ex.: arrow-left em "Voltar para Meus veículos").
    - arrow: seta depois do texto que avança 3px no hover e no foco (só com motion-safe).
    - external: abre em nova aba com rel="noopener", ícone de saída e o aviso "(abre em nova aba)"
      só para leitor de tela.

    Nunca recebe o atributo download (link de PDF é GET simples; ver .ai/rules/js.md).

    Ex.: <x-ui.link :href="route('user.vehicles.index')" arrow>Ver todos</x-ui.link>
         <x-ui.link href="https://www.gov.br/" external variant="inline">portal gov.br</x-ui.link>
--}}
@props([
    'href' => null,
    'variant' => 'standalone',
    'icon' => null,
    'arrow' => false,
    'external' => false,
])
@php
    \App\Support\UiProps::required('x-ui.link', 'href', $href);

    $linkVariant = \App\Support\UiProps::oneOf('x-ui.link', 'variant', $variant, ['standalone', 'inline'], 'standalone');
    $linkIsExternal = (bool) $external;
    $linkIsInline = $linkVariant === 'inline';
    $linkIconClass = $linkIsInline ? 'inline size-[1em] align-[-0.125em]' : 'size-4';
    $linkLeadingIconClass = $linkIsInline ? $linkIconClass.' mr-1' : $linkIconClass;
    $linkTrailingIconClass = $linkIsInline ? $linkIconClass.' ml-1' : $linkIconClass;
    $linkArrowClass = $linkTrailingIconClass.' transition-transform duration-base ease-smooth-out motion-reduce:transition-none motion-safe:group-hover:translate-x-[3px] motion-safe:group-focus-visible:translate-x-[3px]';

    $linkAttributes = $attributes
        ->class([
            'group rounded-sm font-medium text-link underline-offset-4 decoration-1',
            'transition-colors duration-fast ease-smooth-out motion-reduce:transition-none',
            'hover:text-link-hover focus-visible:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
            'inline-flex items-center gap-1.5 hover:underline' => ! $linkIsInline,
            'underline' => $linkIsInline,
        ])
        ->merge([
            'href' => $href,
            'data-slot' => 'link',
            'target' => $linkIsExternal ? '_blank' : null,
            'rel' => $linkIsExternal ? 'noopener' : null,
        ]);
@endphp
{{-- Sem espaço entre as partes: espaço solto dentro do <a> inline viraria sublinhado sobrando. A
     quebra de linha logo depois de @endif é engolida pelo PHP. --}}
<a {{ $linkAttributes }}>@if(filled($icon))<x-ui.icon :name="$icon" :class="$linkLeadingIconClass" />@endif{{ $slot }}@if($linkIsExternal)<x-ui.icon name="arrow-top-right-on-square" :class="$linkTrailingIconClass" /><span class="sr-only"> (abre em nova aba)</span>@endif
@if((bool) $arrow)<x-ui.icon name="arrow-right" :class="$linkArrowClass" />@endif</a>@php
    // Sem quebra de linha depois do elemento: o fechamento deste bloco PHP engole a do fim do
    // arquivo, e o componente não ganha espaço sobrando quando fica no meio do texto.
@endphp

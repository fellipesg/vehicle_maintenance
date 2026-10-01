{{--
    Superfície de conteúdo (card do design system). Substitui .card com sobrescritas "!p-*".

    Props:
    - padding: none | sm (p-4) | md (p-4 sm:p-6, o padrão) | lg (p-6 sm:p-8).
    - as: div (o padrão) | article | section | li | aside. article/section com título ganham
      aria-labelledby apontando para ele.
    - title / description: cabeçalho padrão (título text-base semibold; descrição muted). Também
      aceitam <x-slot:title> e <x-slot:description>.
    - heading-level: h2 | h3 (o padrão) | h4, para não pular nível de título.
    - href: card inteiro clicável. O título vira o único link (stretched link: o ::after do <a>
      cobre o card), então leitor de tela anuncia um link só, com o nome do título. Sem título,
      passe label (texto só para leitor de tela). Hover levanta 2px (motion-safe) e o foco contorna
      o card todo. Botões e links nos slots action e footer continuam clicáveis por cima.
    - label: nome do link quando o card com href não tem título (ou usa o slot header).

    Slots:
    - header: substitui o cabeçalho padrão (título + descrição) por conteúdo próprio.
    - action: à direita do cabeçalho (ex.: x-ui.link "Ver todos" ou x-ui.icon-button).
    - padrão: o corpo.
    - footer: rodapé em linha (botões). Aceita atributos: <x-slot:footer class="border-t pt-4">.

    Ex.: <x-ui.card title="Últimas manutenções" description="As 5 mais recentes.">
             <x-slot:action><x-ui.link :href="route('user.maintenances.index')">Ver todas</x-ui.link></x-slot:action>
             ...
         </x-ui.card>
         <x-ui.card as="article" :href="route('user.vehicles.show', $vehicle)" :title="$vehicle->model">...</x-ui.card>
--}}
@props([
    'padding' => 'md',
    'as' => 'div',
    'href' => null,
    'title' => null,
    'description' => null,
    'headingLevel' => 'h3',
    'label' => null,
])
@php
    $cardPadding = \App\Support\UiProps::oneOf('x-ui.card', 'padding', $padding, ['none', 'sm', 'md', 'lg'], 'md');
    $cardTag = \App\Support\UiProps::oneOf('x-ui.card', 'as', $as, ['div', 'article', 'section', 'li', 'aside'], 'div');
    $cardHeadingTag = \App\Support\UiProps::oneOf('x-ui.card', 'heading-level', $headingLevel, ['h2', 'h3', 'h4'], 'h3');
    $cardHasTitle = ! \App\Support\UiProps::isBlank($title);
    $cardHasDescription = ! \App\Support\UiProps::isBlank($description);
    $cardHasCustomHeader = isset($header) && ! \App\Support\UiProps::isBlank($header);
    $cardHasAction = isset($action) && ! \App\Support\UiProps::isBlank($action);
    $cardHasFooter = isset($footer) && ! \App\Support\UiProps::isBlank($footer);
    $cardIsLink = filled($href);
    $cardTitleIsLink = $cardIsLink && $cardHasTitle && ! $cardHasCustomHeader;
    $cardHiddenLinkLabel = filled($label) ? (string) $label : ($cardHasTitle && ! $cardHasCustomHeader ? null : (is_string($title) ? $title : null));
    $cardNeedsHiddenLink = $cardIsLink && ! $cardTitleIsLink;

    if ($cardNeedsHiddenLink) {
        \App\Support\UiProps::required('x-ui.card', 'title ou label quando tem href', $cardHiddenLinkLabel, 'O link do card precisa de um nome para leitor de tela.');
    }

    $cardTitleId = ($attributes->get('id') ?: 'card-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8))).'-titulo';
    $cardLabelledBy = in_array($cardTag, ['article', 'section'], true) && $cardHasTitle && ! $cardHasCustomHeader ? $cardTitleId : null;

    $cardPaddingClasses = match ($cardPadding) {
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-4 sm:p-6',
        'lg' => 'p-6 sm:p-8',
    };
    $cardLinkClasses = 'text-foreground after:absolute after:inset-0 after:rounded-card focus-visible:outline-hidden focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-ring';
@endphp
<{{ $cardTag }} {{ $attributes->class([
    'flex flex-col gap-4 rounded-card border border-border bg-surface text-foreground shadow-sm',
    $cardPaddingClasses,
    'relative transition-[border-color,box-shadow,translate] duration-fast ease-smooth-out motion-reduce:transition-none hover:border-accent-border hover:shadow-md motion-safe:hover:-translate-y-0.5' => $cardIsLink,
])->merge([
    'data-slot' => 'card',
    'aria-labelledby' => $cardLabelledBy,
]) }}>
    @if($cardHasCustomHeader || $cardHasTitle || $cardHasDescription || $cardHasAction)
        <div data-slot="card-header" @class(['grid items-start gap-x-4 gap-y-1', 'grid-cols-[minmax(0,1fr)_auto]' => $cardHasAction])>
            <div class="min-w-0">
                @if($cardHasCustomHeader)
                    {{ $header }}
                @else
                    @if($cardHasTitle)
                        <{{ $cardHeadingTag }} id="{{ $cardTitleId }}" data-slot="card-title" class="text-base leading-6 font-semibold text-foreground">
                            @if($cardTitleIsLink)
                                <a href="{{ $href }}" data-slot="card-link" class="{{ $cardLinkClasses }}">{{ $title }}</a>
                            @else
                                {{ $title }}
                            @endif
                        </{{ $cardHeadingTag }}>
                    @endif
                    @if($cardHasDescription)
                        <p data-slot="card-description" class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>
                    @endif
                @endif
            </div>
            @if($cardHasAction)
                <div data-slot="card-action" @class(['flex shrink-0 items-center gap-2 self-start', 'relative z-10' => $cardIsLink])>{{ $action }}</div>
            @endif
        </div>
    @endif
    @if($cardNeedsHiddenLink && filled($cardHiddenLinkLabel))
        <a href="{{ $href }}" data-slot="card-link" class="absolute inset-0 rounded-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"><span class="sr-only">{{ $cardHiddenLinkLabel }}</span></a>
    @endif
    @if($slot->hasActualContent())
        <div data-slot="card-content" class="min-w-0">{{ $slot }}</div>
    @endif
    @if($cardHasFooter)
        <div {{ $footer->attributes->class(['flex flex-wrap items-center gap-2', 'relative z-10' => $cardIsLink])->merge(['data-slot' => 'card-footer']) }}>{{ $footer }}</div>
    @endif
</{{ $cardTag }}>

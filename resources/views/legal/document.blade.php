@extends('layouts.app')

@section('title', $title)

@php
    /** @var \App\Support\LegalDocument $document */
    $sections = $document->sections();
    $introduction = $document->introduction();
    $effectiveDate = filled($version) ? \Illuminate\Support\Carbon::parse($version)->locale('pt_BR') : null;

    // O texto vem escapado; só os endereços de e-mail viram link.
    $linkEmails = fn (string $text): string => preg_replace(
        '/[\w.+-]+@[\w-]+(\.[\w-]+)+/',
        '<a href="mailto:$0" class="link underline">$0</a>',
        e($text),
    ) ?? e($text);

    // Links do sumário (o mesmo nas duas versões: recolhível no celular, coluna fixa no desktop).
    $tocLinkClass = 'block rounded-control py-1.5 text-muted-foreground transition-colors duration-fast ease-smooth-out hover:text-foreground motion-reduce:transition-none';
@endphp

@if($introduction)
    @push('head')
        <meta name="description" content="{{ \Illuminate\Support\Str::limit($introduction, 155) }}">
    @endpush
@endif

@section('content')
<x-ui.container size="lg" padded>
    <x-ui.page-header eyebrow="Documentos legais" :title="$title">
        @if($effectiveDate)
            <x-slot:description>
                Em vigor desde <time datetime="{{ $effectiveDate->toDateString() }}">{{ $effectiveDate->translatedFormat('j \d\e F \d\e Y') }}</time>.
            </x-slot:description>
        @endif
        @if(filled($version))
            <p class="text-xs text-subtle-foreground" data-slot="legal-version">Versão {{ $version }}</p>
        @endif
    </x-ui.page-header>

    <div class="lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-start lg:gap-12">
        @if(count($sections) > 1)
            {{-- Celular e tablet: sumário recolhível antes do texto. --}}
            <details class="group mb-8 rounded-card border border-border bg-surface lg:hidden" data-slot="legal-toc-collapsible">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-card px-4 py-2 text-sm font-semibold text-foreground [&::-webkit-details-marker]:hidden">
                    Nesta página
                    <x-ui.icon name="chevron-down" class="size-4 text-muted-foreground transition-transform duration-fast ease-smooth-out group-open:rotate-180 motion-reduce:transition-none" />
                </summary>
                <nav aria-label="Nesta página" class="border-t border-border px-4 py-2">
                    <ol role="list" class="text-sm">
                        @foreach($sections as $section)
                            <li><a href="#{{ $section['id'] }}" class="{{ $tocLinkClass }} max-sm:py-2.5">{{ $section['text'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            </details>

            {{-- Desktop: coluna fixa ao lado do texto, abaixo da barra do topo (64px). --}}
            <nav aria-labelledby="legal-toc-title" class="hidden lg:sticky lg:top-24 lg:block lg:max-h-[calc(100dvh-8rem)] lg:overflow-y-auto" data-slot="legal-toc">
                <p id="legal-toc-title" class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">Nesta página</p>
                <ol role="list" class="mt-3 border-l border-border text-sm">
                    @foreach($sections as $section)
                        <li><a href="#{{ $section['id'] }}" class="{{ $tocLinkClass }} -ml-px border-l-2 border-transparent pl-4 hover:border-border-strong">{{ $section['text'] }}</a></li>
                    @endforeach
                </ol>
            </nav>
        @endif

        {{-- Coluna de leitura: ~72 caracteres por linha (56ch na Inter), 17–18px e entrelinha 1,8. --}}
        <div class="doc-content min-w-0 max-w-[56ch] text-[1.0625rem] leading-[1.8] text-foreground sm:text-lg" data-slot="legal-content">
            @foreach($document->blocks as $block)
                @if($block['type'] === 'heading')
                    <h2 id="{{ $block['id'] }}" class="mt-10 scroll-mt-24 text-xl leading-snug font-semibold text-balance text-foreground first:mt-0">{{ $block['text'] }}</h2>
                @elseif($block['type'] === 'list')
                    <ul class="mt-4 list-disc space-y-2 pl-6 marker:text-subtle-foreground first:mt-0">
                        @foreach($block['items'] as $item)
                            <li class="pl-1">{!! $linkEmails($item) !!}</li>
                        @endforeach
                    </ul>
                @elseif($loop->first)
                    <p class="text-lg leading-relaxed text-muted-foreground sm:text-xl" data-slot="legal-introduction">{!! $linkEmails($block['text']) !!}</p>
                @else
                    <p class="mt-4 first:mt-0">{!! $linkEmails($block['text']) !!}</p>
                @endif
            @endforeach

            <div class="mt-12 space-y-2 border-t border-border pt-6 text-base leading-7 text-muted-foreground">
                <p>Ficou alguma dúvida? <x-ui.link :href="$contactUrl" variant="inline">Fale conosco</x-ui.link>.</p>
                <p>Leia também: <x-ui.link :href="$relatedDocument['url']" variant="inline">{{ $relatedDocument['label'] }}</x-ui.link>.</p>
            </div>
        </div>
    </div>
</x-ui.container>
@endsection

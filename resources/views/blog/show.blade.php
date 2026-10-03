@extends('layouts.app')

@php
    $metaTitle = $post->meta_title ?: $post->title;
    $metaDescription = $post->meta_description ?: $post->summary;
    $publishedAt = \App\Support\DisplayTime::local($post->published_at)?->locale('pt_BR');
    $updatedAt = \App\Support\DisplayTime::local($post->updated_at)?->locale('pt_BR');
    // "Atualizado em" só quando a revisão veio depois do dia da publicação.
    $showUpdatedAt = $publishedAt !== null && $updatedAt !== null && $updatedAt->toDateString() > $publishedAt->toDateString();
    $breadcrumbs = array_values(array_filter([
        ['Blog', route('blog.index')],
        $post->category ? [$post->category->name, route('blog.category', $post->category)] : null,
        [$post->title],
    ]));
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'description' => $metaDescription,
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => $post->updated_at?->toIso8601String(),
        'image' => $post->cover_photo_url,
        'mainEntityOfPage' => route('blog.show', $post),
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('legal.company.legal_name') ?: 'RevisaLog',
        ],
    ];
    // Coluna de leitura, a mesma no cabeçalho, no texto e no convite ao fim, para as margens se
    // alinharem. Na Inter 1ch (a largura do "0") vale ~1,3 letra média: 56ch dão ~72 caracteres por
    // linha a 17–18px (68ch passariam de 85, acima dos 60–75 recomendados).
    $readingColumn = 'mx-auto w-full max-w-[56ch] text-[1.0625rem] sm:text-lg';
@endphp

@section('title', $metaTitle)

{{-- A imagem de compartilhamento do post: o layout imprime og:image e twitter:image uma vez só,
     com esta capa no lugar da arte padrão da marca. --}}
@if($post->cover_photo_url)
    @section('og_image', $post->cover_photo_url)
    @section('og_image_alt', $post->cover_photo_alt ?: $post->title)
@endif

@push('head')
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ route('blog.show', $post) }}">
    <link rel="alternate" type="application/rss+xml" title="Blog RevisaLog" href="{{ route('blog.feed') }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ route('blog.show', $post) }}">
    @if($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @endif
    @if($post->category)
        <meta property="article:section" content="{{ $post->category->name }}">
    @endif
    <script type="application/ld+json">
        {!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('content')
<x-ui.container as="article" size="lg" padded>
    <div class="{{ $readingColumn }}">
        @unless($post->isPublished())
            <x-ui.alert variant="warning" role="status" title="Pré-visualização" class="mb-6">
                Este post ainda não está publicado. Só administradores veem esta página.
            </x-ui.alert>
        @endunless

        <x-ui.page-header :breadcrumbs="$breadcrumbs">
            <x-slot:title><span class="block text-3xl leading-tight sm:text-4xl lg:text-5xl lg:leading-[1.1]" data-slot="blog-post-title">{{ $post->title }}</span></x-slot:title>
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" data-slot="blog-post-meta">
                @if($publishedAt)
                    <time datetime="{{ $publishedAt->toDateString() }}">{{ $publishedAt->translatedFormat('j \d\e F \d\e Y') }}</time>
                    <span aria-hidden="true">·</span>
                @endif
                <span>{{ $post->reading_minutes }} min de leitura</span>
                @if($post->author)
                    <span aria-hidden="true">·</span>
                    <span>por {{ $post->author->name }}</span>
                @endif
            </p>
            @if($showUpdatedAt)
                <p class="mt-1 text-sm text-muted-foreground" data-slot="blog-post-updated">
                    Atualizado em <time datetime="{{ $updatedAt->toDateString() }}">{{ $updatedAt->translatedFormat('j \d\e F \d\e Y') }}</time>
                </p>
            @endif
        </x-ui.page-header>

        @if($post->excerpt)
            {{-- Lead: o resumo do post, maior e sem a borda do blockquote, para não parecer citação. --}}
            <p class="text-lg leading-relaxed text-pretty text-muted-foreground sm:text-xl" data-slot="blog-post-lead">{{ $post->excerpt }}</p>
        @endif
    </div>

    <figure class="mx-auto mt-8 max-w-4xl overflow-hidden rounded-card border border-border bg-surface-muted shadow-sm" data-slot="blog-post-cover">
        @if($post->cover_photo_url)
            <img
                src="{{ $post->cover_photo_url }}"
                alt="{{ $post->cover_photo_alt ?: $post->title }}"
                width="1200"
                height="630"
                fetchpriority="high"
                decoding="async"
                class="aspect-[1200/630] w-full object-contain"
            >
        @else
            <x-blog.cover :post="$post" class="aspect-[1200/630] w-full" />
        @endif
    </figure>

    {{-- Markdown do post (.ai/rules/blog.md): estilos de .blog-content em resources/css/app.css. --}}
    <div class="blog-content mt-10 leading-[1.8] {{ $readingColumn }}" data-slot="blog-post-content">
        {!! Str::markdown($post->content) !!}
    </div>

    <aside aria-labelledby="blog-cta-titulo" class="{{ $readingColumn }} mt-12 rounded-card border border-accent-border bg-accent p-6 sm:p-8" data-slot="blog-post-cta">
        <h2 id="blog-cta-titulo" class="text-lg font-semibold text-balance text-foreground">Guarde o histórico do seu carro no RevisaLog</h2>
        <p class="mt-2 text-base leading-7 text-muted-foreground">
            Cada revisão registrada fica vinculada ao veículo, não à conta. Na hora de vender, o histórico vai junto.
        </p>
        <div class="mt-5 flex flex-wrap gap-3 max-sm:*:grow">
            @guest
                <x-ui.button :href="route('register')">Começar grátis</x-ui.button>
            @endguest
            <x-ui.button variant="secondary" :href="route('home')">Conhecer a plataforma</x-ui.button>
        </div>
    </aside>

    @if($related->isNotEmpty())
        <x-ui.section id="leia-tambem" title="Leia também" class="mt-16 border-t border-border pt-10">
            <ul role="list" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($related as $item)
                    <li class="min-w-0">
                        <x-blog.card :post="$item" heading-level="h3" />
                    </li>
                @endforeach
            </ul>
        </x-ui.section>
    @endif
</x-ui.container>
@endsection

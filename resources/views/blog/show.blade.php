@extends('layouts.app')

@php
    $metaTitle = $post->meta_title ?: $post->title;
    $metaDescription = $post->meta_description ?: $post->summary;
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
@endphp

@section('title', $metaTitle)

@push('head')
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ route('blog.show', $post) }}">
    <link rel="alternate" type="application/rss+xml" title="Blog RevisaLog" href="{{ route('blog.feed') }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ route('blog.show', $post) }}">
    @if($post->cover_photo_url)
        <meta property="og:image" content="{{ $post->cover_photo_url }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    @if($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @endif
    <script type="application/ld+json">
        {!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('content')
<article class="mx-auto max-w-3xl px-4 py-10">
    @unless($post->isPublished())
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Pré-visualização: este post ainda não está publicado.
        </div>
    @endunless

    <nav class="flex flex-wrap items-center gap-2 text-sm text-automotive-500" aria-label="Trilha">
        <a href="{{ route('blog.index') }}" class="text-wrench-600 hover:underline">Blog</a>
        @if($post->category)
            <span aria-hidden="true">/</span>
            <a href="{{ route('blog.category', $post->category) }}" class="text-wrench-600 hover:underline">
                {{ $post->category->name }}
            </a>
        @endif
    </nav>

    <h1 class="mt-3 text-4xl font-bold leading-tight text-automotive-900">{{ $post->title }}</h1>

    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-automotive-500">
        <time datetime="{{ $post->published_at?->toDateString() }}">
            {{ $post->published_at?->format('d/m/Y') }}
        </time>
        <span aria-hidden="true">·</span>
        <span>{{ $post->reading_minutes }} min de leitura</span>
        @if($post->author)
            <span aria-hidden="true">·</span>
            <span>por {{ $post->author->name }}</span>
        @endif
    </div>

    @if($post->excerpt)
        <p class="mt-5 border-l-4 border-wrench-500 pl-4 text-lg leading-relaxed text-automotive-700">
            {{ $post->excerpt }}
        </p>
    @endif

    @if($post->cover_photo_url)
        <img
            src="{{ $post->cover_photo_url }}"
            alt="{{ $post->cover_photo_alt ?: $post->title }}"
            class="mt-8 w-full rounded-xl border border-automotive-200 object-cover shadow-sm"
        >
    @else
        <div class="mt-8 overflow-hidden rounded-xl border border-automotive-200 shadow-sm">
            <x-blog.cover :post="$post" class="aspect-[1200/630] w-full" />
        </div>
    @endif

    <div class="blog-content mt-8">
        {!! Str::markdown($post->content) !!}
    </div>

    <aside class="mt-12 rounded-xl border border-wrench-500/30 bg-wrench-500/10 p-6">
        <h2 class="text-lg font-bold text-automotive-900">Guarde o histórico do seu carro no RevisaLog</h2>
        <p class="mt-2 text-sm leading-relaxed text-automotive-700">
            Cada revisão registrada fica vinculada ao veículo — não à conta. Na hora de vender, o histórico vai junto.
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
            @guest
                <a href="{{ route('register') }}" class="btn-primary">Começar grátis</a>
            @endguest
            <a href="{{ route('home') }}" class="btn-secondary">Conhecer a plataforma</a>
        </div>
    </aside>

    @if($related->isNotEmpty())
        <section class="mt-12">
            <h2 class="mb-4 text-xl font-bold text-automotive-900">Leia também</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                @foreach($related as $item)
                    <x-blog.card :post="$item" />
                @endforeach
            </div>
        </section>
    @endif
</article>
@endsection

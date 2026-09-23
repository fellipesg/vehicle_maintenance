@extends('layouts.app')

@php
    $pageTitle = $category ? $category->name.' — Blog' : 'Blog';
    $pageDescription = $category?->description
        ?: 'Guias, dicas e novidades sobre manutenção, documentação e cuidados com o seu carro.';
@endphp

@section('title', $pageTitle)

@push('head')
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ $category ? route('blog.category', $category) : route('blog.index') }}">
    <link rel="alternate" type="application/rss+xml" title="Blog RevisaLog" href="{{ route('blog.feed') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
@endpush

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10">
    <header class="mb-8">
        @if($category)
            <a href="{{ route('blog.index') }}" class="text-sm text-wrench-600 hover:underline">← Todos os artigos</a>
        @endif
        <h1 class="mt-2 text-4xl font-bold text-automotive-900">{{ $category?->name ?? 'Blog' }}</h1>
        <p class="mt-2 max-w-2xl text-automotive-600">{{ $pageDescription }}</p>
    </header>

    @if($categories->isNotEmpty())
        <nav class="mb-8 flex flex-wrap gap-2" aria-label="Categorias do blog">
            <a
                href="{{ route('blog.index') }}"
                @class([
                    'rounded-full border px-3 py-1.5 text-sm transition',
                    'border-wrench-500 bg-wrench-500/15 font-semibold text-wrench-700' => ! $category,
                    'border-automotive-200 bg-white text-automotive-600 hover:border-wrench-400 hover:text-wrench-700' => (bool) $category,
                ])
            >
                Todos
            </a>
            @foreach($categories as $item)
                <a
                    href="{{ route('blog.category', $item) }}"
                    @class([
                        'rounded-full border px-3 py-1.5 text-sm transition',
                        'border-wrench-500 bg-wrench-500/15 font-semibold text-wrench-700' => $category?->is($item),
                        'border-automotive-200 bg-white text-automotive-600 hover:border-wrench-400 hover:text-wrench-700' => ! $category?->is($item),
                    ])
                >
                    {{ $item->name }}
                    <span class="text-xs text-automotive-400">{{ $item->posts_count }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    @if($posts->isEmpty())
        <div class="card text-center text-automotive-500">
            Nenhum artigo publicado {{ $category ? 'nesta categoria' : 'por enquanto' }}.
        </div>
    @else
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                <x-blog.card :post="$post" />
            @endforeach
        </div>

        <div class="mt-10">
            {{ $posts->links() }}
        </div>
    @endif
</div>
@endsection

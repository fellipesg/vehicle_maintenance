@extends('layouts.app')

@php
    $pageTitle = $category ? $category->name.' — Blog' : 'Blog';
    $pageDescription = $category?->description
        ?: 'Guias, dicas e novidades sobre manutenção, documentação e cuidados com o seu carro.';

    // Chips de categoria: estado pelo atributo aria-current (não só pela cor) e alvo de 40px.
    $chipClass = 'inline-flex min-h-10 shrink-0 snap-start items-center gap-1.5 rounded-full border px-4 text-sm whitespace-nowrap transition-colors duration-fast ease-smooth-out motion-reduce:transition-none';
    $chipIdleClass = 'border-border bg-surface font-medium text-muted-foreground hover:border-border-strong hover:text-foreground';
    $chipCurrentClass = 'border-accent-border bg-accent font-semibold text-accent-foreground';
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
<x-ui.container padded>
    <x-ui.page-header
        :title="$category?->name ?? 'Blog'"
        :description="$pageDescription"
        :breadcrumbs="$category ? [['Blog', route('blog.index')], [$category->name]] : []"
    />

    @if($categories->isNotEmpty())
        {{-- No celular os chips rolam na horizontal em vez de ocupar várias linhas antes dos posts. --}}
        <nav aria-label="Categorias do blog" class="mb-8" data-slot="blog-categories">
            <ul role="list" class="landing-scroller -mx-4 flex snap-x scroll-px-4 gap-2 overflow-x-auto px-4 py-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
                <li class="shrink-0">
                    <a
                        href="{{ route('blog.index') }}"
                        @if(! $category) aria-current="page" @endif
                        class="{{ $chipClass }} {{ $category ? $chipIdleClass : $chipCurrentClass }}"
                    >Todos</a>
                </li>
                @foreach($categories as $item)
                    @php($isCurrentCategory = (bool) $category?->is($item))
                    <li class="shrink-0">
                        <a
                            href="{{ route('blog.category', $item) }}"
                            @if($isCurrentCategory) aria-current="page" @endif
                            class="{{ $chipClass }} {{ $isCurrentCategory ? $chipCurrentClass : $chipIdleClass }}"
                        >
                            {{ $item->name }}
                            <span class="text-xs font-normal tabular-nums text-subtle-foreground" aria-hidden="true">{{ $item->posts_count }}</span>
                            <span class="sr-only">({{ $item->posts_count === 1 ? '1 artigo' : $item->posts_count.' artigos' }})</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    @if($posts->isEmpty())
        <x-ui.empty-state
            icon="newspaper"
            heading-level="h2"
            :title="$category ? 'Ainda não há artigos nesta categoria' : 'Ainda não há artigos aqui'"
            description="Os próximos guias sobre manutenção, documentação e venda do carro aparecem nesta página."
        >
            <x-slot:actions>
                @if($category)
                    <x-ui.button :href="route('blog.index')">Ver todos os artigos</x-ui.button>
                @endif
                <x-ui.button variant="secondary" :href="route('home')">Conhecer a plataforma</x-ui.button>
            </x-slot:actions>
        </x-ui.empty-state>
    @else
        <ul role="list" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-slot="blog-posts">
            @foreach($posts as $post)
                <li class="min-w-0">
                    <x-blog.card :post="$post" />
                </li>
            @endforeach
        </ul>

        @if($posts->hasPages())
            <div class="mt-10">
                {{ $posts->onEachSide(1)->links() }}
            </div>
        @endif
    @endif
</x-ui.container>
@endsection

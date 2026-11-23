@extends('layouts.app')

@php
    $metaTitle = $post->meta_title ?: $post->title;
    $metaDescription = $post->meta_description ?: $post->summary;
    $publishedAt = \App\Support\DisplayTime::local($post->published_at)?->locale('pt_BR');
    $updatedAt = \App\Support\DisplayTime::local($post->updated_at)?->locale('pt_BR');
    // "Atualizado em" só quando a revisão veio depois do dia da publicação.
    $showUpdatedAt = $publishedAt !== null && $updatedAt !== null && $updatedAt->toDateString() > $publishedAt->toDateString();
    $article = \App\Support\Seo\BlogArticle::split($post->content);
    $articleLead = $article['lead'] ?: $post->excerpt;
    $articleFaqs = \App\Support\Seo\BlogArticle::faqs($article['body']);
    $canonical = \App\Support\Seo\CanonicalUrl::named('blog.show', $post);
    $breadcrumbs = array_values(array_filter([
        ['Início', \App\Support\Seo\CanonicalUrl::home()],
        ['Blog', route('blog.index')],
        $post->category ? [$post->category->name, route('blog.category', $post->category)] : null,
        [$post->title],
    ]));
    $breadcrumbSchema = array_values(array_filter([
        ['name' => 'Início', 'url' => \App\Support\Seo\CanonicalUrl::home()],
        ['name' => 'Blog', 'url' => \App\Support\Seo\CanonicalUrl::named('blog.index')],
        $post->category ? ['name' => $post->category->name, 'url' => \App\Support\Seo\CanonicalUrl::named('blog.category', $post->category)] : null,
        ['name' => $post->title, 'url' => $canonical],
    ]));
    // Coluna de leitura, a mesma no cabeçalho, no texto e no convite ao fim, para as margens se
    // alinharem. Na Inter 1ch (a largura do "0") vale ~1,3 letra média: 56ch dão ~72 caracteres por
    // linha a 17–18px (68ch passariam de 85, acima dos 60–75 recomendados).
    $readingColumn = 'mx-auto w-full max-w-[56ch] text-[1.0625rem] sm:text-lg';
@endphp

@section('title', $metaTitle)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('og_type', 'article')

{{-- A imagem de compartilhamento do post: o layout imprime og:image e twitter:image uma vez só,
     com esta capa no lugar da arte padrão da marca. --}}
@if($post->cover_photo_url)
    @section('og_image', $post->cover_photo_url)
    @section('og_image_alt', $post->cover_photo_alt ?: $post->title)
@endif

@push('head')
    <link rel="alternate" type="application/rss+xml" title="Blog RevisaLog" href="{{ route('blog.feed') }}">
    @if($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @endif
    @if($post->category)
        <meta property="article:section" content="{{ $post->category->name }}">
    @endif
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::blogPosting($post, $publishedCommentCount)" />
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::breadcrumb($breadcrumbSchema)" />
    @if($articleFaqs !== [])
        <x-seo.json-ld :data="\App\Support\Seo\StructuredData::faqPage($articleFaqs)" />
    @endif
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

        @if($articleLead)
            {{-- Lead: a resposta direta do post, ou o resumo quando o texto não abre com citação. --}}
            <p class="text-lg leading-relaxed text-pretty text-muted-foreground sm:text-xl" data-slot="blog-post-lead">{{ $articleLead }}</p>
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
        {!! Str::markdown($article['body']) !!}
    </div>

    @if($post->isPublished())
        <x-ads.unit placement="blog_article" class="{{ $readingColumn }} mt-12" />
    @endif

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

    {{-- Comentários (.ai/rules/blog.md): depois do texto, do anúncio e do convite; nunca dentro de .blog-content. --}}
    <section id="comentarios" aria-labelledby="comentarios-titulo" class="{{ $readingColumn }} mt-12 scroll-mt-24 border-t border-border pt-10" data-slot="blog-comments">
        <h2 id="comentarios-titulo" class="text-xl font-semibold text-foreground">Comentários ({{ $publishedCommentCount }})</h2>

        @if($comments->isEmpty())
            <p class="mt-4 text-base text-muted-foreground" data-slot="blog-comments-empty">Ainda não há comentários. Seja a primeira pessoa a comentar.</p>
        @else
            <ul role="list" class="mt-6 space-y-4">
                @foreach($comments as $comment)
                    @php $commentAuthorIsStaff = $comment->user?->isAdmin() === true; @endphp
                    <li class="rounded-card border border-border bg-surface p-4" data-slot="blog-comment" id="comentario-{{ $comment->id }}">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                            <span class="font-medium text-foreground" data-slot="blog-comment-author">{{ $comment->author_display_name }}</span>
                            @if($commentAuthorIsStaff)
                                <x-ui.badge variant="primary" size="sm">Equipe RevisaLog</x-ui.badge>
                            @endif
                            @if($comment->isPending())
                                <x-ui.badge variant="warning" size="sm" icon="clock">Aguardando moderação</x-ui.badge>
                            @endif
                            <span aria-hidden="true" class="text-muted-foreground">·</span>
                            <time class="text-muted-foreground" datetime="{{ $comment->created_at->toIso8601String() }}">{{ \App\Support\DisplayTime::local($comment->created_at)->format('d/m/Y \à\s H:i') }}</time>
                        </div>
                        <p class="mt-2 text-base leading-7 break-words whitespace-pre-line text-foreground" data-slot="blog-comment-body">{{ $comment->body }}</p>
                        @can('delete', $comment)
                            <form method="POST" action="{{ route('blog.comments.destroy', $comment) }}" class="mt-3"
                                  data-confirm="Seu comentário será excluído do artigo. Não é possível desfazer."
                                  data-confirm-title="Excluir o comentário?"
                                  data-confirm-action-label="Excluir comentário"
                                  data-confirm-variant="danger">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" size="sm" icon="trash">Excluir<span class="sr-only"> meu comentário</span></x-ui.button>
                            </form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8" data-slot="blog-comment-form">
            @if(! $post->comments_enabled)
                <x-ui.alert variant="info" role="status">Os comentários estão fechados neste artigo.</x-ui.alert>
            @elseif(! $post->isPublished())
                <x-ui.alert variant="info" role="status">Os comentários abrem quando o artigo for publicado.</x-ui.alert>
            @elseif(auth()->check())
                <form method="POST" action="{{ route('blog.comments.store', $post) }}" class="relative grid gap-4">
                    @csrf

                    {{-- Armadilha para robôs: fora da tela, sem foco e escondida do leitor de tela. --}}
                    <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                        <label for="comment-website">Website</label>
                        <input type="text" name="website" id="comment-website" tabindex="-1" autocomplete="off">
                    </div>

                    <x-ui.field name="body" label="Seu comentário" hint="Até 2.000 caracteres. Comentários com link são revisados antes de aparecer.">
                        <x-ui.textarea rows="4" maxlength="2000" counter required />
                    </x-ui.field>

                    <div>
                        <x-ui.button type="submit" icon="chat-bubble-left-right" loading-label="Publicando…">Publicar comentário</x-ui.button>
                    </div>
                </form>
            @else
                <div class="rounded-card border border-border bg-surface-muted p-5" data-slot="blog-comment-guest-cta">
                    <p class="text-base font-medium text-foreground">Entre para comentar</p>
                    <p class="mt-1 text-sm text-muted-foreground">Só quem tem conta no RevisaLog comenta. É grátis e você volta direto para este artigo.</p>
                    <div class="mt-4 flex flex-wrap gap-3 max-sm:*:grow">
                        <x-ui.button :href="route('blog.comments.enter', [$post, 'entrar'])">Entre para comentar</x-ui.button>
                        <x-ui.button variant="secondary" :href="route('blog.comments.enter', [$post, 'criar-conta'])">Criar conta</x-ui.button>
                    </div>
                </div>
            @endif
        </div>
    </section>

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

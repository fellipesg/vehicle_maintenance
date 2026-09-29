{{--
    Card de post do blog (listagem, categoria e "Leia também").

    Props:
    - post: o BlogPost (com category carregada).
    - heading-level: h2 (o padrão, na listagem) | h3 (dentro de uma seção com h2, como "Leia também") | h4.

    Um link só por card (stretched link): o título cobre o card inteiro pelo ::after, então o
    teclado para uma vez e o leitor de tela anuncia o título. A capa é decorativa (alt vazio ou
    aria-hidden), porque repetiria o título. O chip da categoria fica por cima (relative z-10) e
    continua sendo outro link. Hover e foco levantam o card 2px e aproximam a capa, só com
    motion-safe; com prefers-reduced-motion nada se mexe.
--}}
@props(['post', 'headingLevel' => 'h2'])

@php
    $cardHeadingTag = \App\Support\UiProps::oneOf('x-blog.card', 'heading-level', $headingLevel, ['h2', 'h3', 'h4'], 'h2');
    $cardPublishedAt = \App\Support\DisplayTime::local($post->published_at)?->locale('pt_BR');
    $cardMediaMotion = 'transition-transform duration-slow ease-smooth-out motion-reduce:transition-none motion-safe:group-hover:scale-[1.03] motion-safe:group-focus-within:scale-[1.03]';
@endphp

<article {{ $attributes->class([
    'group relative flex h-full flex-col rounded-card border border-border bg-surface shadow-sm',
    'transition-[border-color,box-shadow,translate] duration-fast ease-smooth-out motion-reduce:transition-none',
    'hover:border-accent-border hover:shadow-md motion-safe:hover:-translate-y-0.5',
    'focus-within:border-accent-border focus-within:shadow-md motion-safe:focus-within:-translate-y-0.5',
])->merge(['data-slot' => 'blog-card']) }}>
    {{-- O card não corta o conteúdo (o contorno de foco sai 2px para fora); a moldura da capa
         recorta só o zoom do hover. A foto aparece inteira (object-contain) sobre bg-surface-muted:
         na proporção pedida no admin (1200 × 630) ela preenche a moldura, fora dela nada é cortado. --}}
    <div class="overflow-hidden rounded-t-[calc(var(--radius-card)-1px)] bg-surface-muted" data-slot="blog-card-media">
        @if($post->cover_photo_url)
            <img
                src="{{ $post->cover_photo_url }}"
                alt=""
                width="1200"
                height="630"
                loading="lazy"
                decoding="async"
                class="aspect-[1200/630] w-full object-contain {{ $cardMediaMotion }}"
            >
        @else
            <x-blog.cover
                :post="$post"
                :label="false"
                decorative
                class="aspect-[1200/630] w-full {{ $cardMediaMotion }}"
            />
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-5">
        @if($post->category)
            <a
                href="{{ route('blog.category', $post->category) }}"
                class="relative z-10 self-start rounded-sm text-xs font-semibold tracking-wide text-link uppercase underline-offset-4 transition-colors duration-fast ease-smooth-out hover:text-link-hover hover:underline motion-reduce:transition-none"
                data-slot="blog-card-category"
            >{{ $post->category->name }}</a>
        @endif

        <{{ $cardHeadingTag }} class="text-lg leading-snug font-semibold text-balance text-foreground" data-slot="blog-card-title">
            <a
                href="{{ route('blog.show', $post) }}"
                class="transition-colors duration-fast ease-smooth-out group-hover:text-link after:absolute after:inset-0 after:rounded-card focus-visible:outline-hidden focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-ring motion-reduce:transition-none"
            >{{ $post->title }}</a>
        </{{ $cardHeadingTag }}>

        @if(filled($post->summary))
            <p class="line-clamp-3 text-sm leading-6 text-muted-foreground">{{ $post->summary }}</p>
        @endif

        <p class="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1 pt-2 text-xs text-subtle-foreground">
            @if($cardPublishedAt)
                <time datetime="{{ $cardPublishedAt->toDateString() }}">{{ $cardPublishedAt->translatedFormat('j \d\e M. \d\e Y') }}</time>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ $post->reading_minutes }} min de leitura</span>
        </p>
    </div>
</article>

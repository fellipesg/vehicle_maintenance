@props(['post'])

<article class="group flex h-full flex-col overflow-hidden rounded-xl border border-automotive-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
    <a href="{{ route('blog.show', $post) }}" class="block overflow-hidden bg-automotive-100">
        @if($post->cover_photo_url)
            <img
                src="{{ $post->cover_photo_url }}"
                alt="{{ $post->cover_photo_alt ?: $post->title }}"
                loading="lazy"
                class="aspect-[1200/630] w-full object-cover transition duration-500 group-hover:scale-[1.03]"
            >
        @else
            <x-blog.cover
                :post="$post"
                :label="false"
                class="aspect-[1200/630] w-full transition duration-500 group-hover:scale-[1.03]"
            />
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-3 p-5">
        @if($post->category)
            <a
                href="{{ route('blog.category', $post->category) }}"
                class="text-xs font-semibold uppercase tracking-wide text-wrench-600 hover:text-wrench-700"
            >
                {{ $post->category->name }}
            </a>
        @endif

        <h2 class="text-lg font-bold leading-snug text-automotive-900">
            <a href="{{ route('blog.show', $post) }}" class="hover:text-wrench-700">{{ $post->title }}</a>
        </h2>

        <p class="line-clamp-3 text-sm leading-relaxed text-automotive-600">{{ $post->summary }}</p>

        <div class="mt-auto flex items-center gap-2 pt-2 text-xs text-automotive-500">
            <time datetime="{{ $post->published_at?->toDateString() }}">
                {{ $post->published_at?->format('d/m/Y') }}
            </time>
            <span aria-hidden="true">·</span>
            <span>{{ $post->reading_minutes }} min de leitura</span>
        </div>
    </div>
</article>

<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>Blog RevisaLog</title>
        <link>{{ route('blog.index') }}</link>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
        <description>Guias, dicas e novidades sobre manutenção, documentação e cuidados com o seu carro.</description>
        <language>pt-BR</language>
        <lastBuildDate>{{ $posts->first()?->published_at?->toRfc2822String() ?? now()->toRfc2822String() }}</lastBuildDate>
        @foreach($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('blog.show', $post) }}</link>
            <guid isPermaLink="true">{{ route('blog.show', $post) }}</guid>
            <pubDate>{{ $post->published_at?->toRfc2822String() }}</pubDate>
            @if($post->category)
            <category>{{ $post->category->name }}</category>
            @endif
            <description>{{ $post->summary }}</description>
        </item>
        @endforeach
    </channel>
</rss>

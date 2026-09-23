@props(['post', 'label' => true])

@php
    $art = \App\Support\BlogCoverArt::for($post->category, $post->cover_art);
    $uid = 'blog-cover-'.($post->getKey() ?? 'novo').'-'.substr(md5($post->slug ?? $post->title ?? 'post'), 0, 6);
@endphp

<svg
    {{ $attributes->merge(['class' => 'block w-full']) }}
    viewBox="0 0 1200 630"
    preserveAspectRatio="xMidYMid slice"
    role="img"
    aria-label="Ilustração de {{ $art['label'] }}"
    data-scene="{{ $art['scene'] }}"
    xmlns="http://www.w3.org/2000/svg"
>
    <defs>
        <linearGradient id="{{ $uid }}-bg" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="{{ $art['from'] }}" />
            <stop offset="100%" stop-color="{{ $art['to'] }}" />
        </linearGradient>
        <radialGradient id="{{ $uid }}-glow" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="{{ $art['accent'] }}" stop-opacity="0.32" />
            <stop offset="100%" stop-color="{{ $art['accent'] }}" stop-opacity="0" />
        </radialGradient>
        <pattern id="{{ $uid }}-dots" width="28" height="28" patternUnits="userSpaceOnUse">
            <circle cx="1.5" cy="1.5" r="1.5" fill="#ffffff" fill-opacity="0.08" />
        </pattern>
    </defs>

    <rect width="1200" height="630" fill="url(#{{ $uid }}-bg)" />
    <rect width="1200" height="630" fill="url(#{{ $uid }}-dots)" />
    <circle cx="1010" cy="120" r="300" fill="url(#{{ $uid }}-glow)" />

    {{-- A cena é recuada para baixo para não disputar espaço com o rótulo da categoria. --}}
    <g transform="translate(36, 34) scale(0.94)">
        <x-dynamic-component :component="'blog.art.'.$art['scene']" :art="$art" />
    </g>

    <rect x="0" y="606" width="1200" height="24" fill="{{ $art['accent'] }}" fill-opacity="0.85" />

    @if($label)
        <text
            x="72"
            y="120"
            fill="{{ $art['accent'] }}"
            font-size="30"
            font-weight="700"
            letter-spacing="6"
            font-family="Inter, ui-sans-serif, system-ui, sans-serif"
        >{{ Str::upper($art['label']) }}</text>
    @endif
</svg>

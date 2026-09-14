@props([
    'vehicle',
    'variant' => 'thumb',
])

@php
    $landscapeUrl = $vehicle->cover_photo_url;
    $portraitUrl = $vehicle->cover_photo_portrait_url ?? $landscapeUrl;
    $landscapeUrl = $landscapeUrl ?? $portraitUrl;
    $displayUrl = $portraitUrl ?? $landscapeUrl;
    $alt = 'Capa do '.$vehicle->brand.' '.$vehicle->model;
    $isThumb = $variant === 'thumb';
    $frame = match ($variant) {
        'hero' => 'relative w-full overflow-hidden rounded-xl bg-automotive-100',
        'card' => 'relative w-full overflow-hidden bg-automotive-100',
        default => 'relative h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-automotive-100',
    };
    $imageClass = 'absolute inset-0 h-full w-full object-cover object-center';
    $pictureClass = 'absolute inset-0 block h-full w-full';
@endphp

<div {{ $attributes->merge(['class' => $frame]) }}>
    @if ($displayUrl)
        @if ($isThumb || ! $landscapeUrl || $landscapeUrl === $portraitUrl)
            <img src="{{ $displayUrl }}" alt="{{ $alt }}" class="{{ $imageClass }}">
        @else
            <picture class="{{ $pictureClass }}">
                <source media="(min-width: 768px)" srcset="{{ $landscapeUrl }}">
                <img src="{{ $portraitUrl ?? $landscapeUrl }}" alt="{{ $alt }}" class="{{ $imageClass }}">
            </picture>
        @endif
    @else
        <div class="flex h-full min-h-24 w-full items-center justify-center text-automotive-400 {{ $isThumb ? '' : 'py-12' }}" aria-hidden="true">
            <svg class="h-1/2 w-1/2 max-h-10 max-w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 16.5h16.5M5.25 16.5l1.2-6.3A1.5 1.5 0 0 1 7.92 9h8.16a1.5 1.5 0 0 1 1.47 1.2l1.2 6.3M7.5 16.5v1.125a1.125 1.125 0 0 1-2.25 0V16.5m13.5 0v1.125a1.125 1.125 0 0 1-2.25 0V16.5M6.75 12h10.5" />
            </svg>
        </div>
    @endif
</div>

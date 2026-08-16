@props([
    'vehicle',
    'variant' => 'thumb',
])

@php
    $url = $vehicle->cover_photo_url;
    $alt = 'Capa do '.$vehicle->brand.' '.$vehicle->model;
    $frame = match ($variant) {
        'hero' => 'aspect-[16/9] w-full overflow-hidden rounded-xl bg-automotive-100',
        'card' => 'aspect-[16/9] w-full overflow-hidden bg-automotive-100',
        default => 'h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-automotive-100',
    };
@endphp

<div {{ $attributes->merge(['class' => $frame]) }}>
    @if ($url)
        <img src="{{ $url }}" alt="{{ $alt }}" class="h-full w-full object-cover">
    @else
        <div class="flex h-full w-full items-center justify-center text-automotive-400" aria-hidden="true">
            <svg class="h-1/2 w-1/2 max-h-10 max-w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 16.5h16.5M5.25 16.5l1.2-6.3A1.5 1.5 0 0 1 7.92 9h8.16a1.5 1.5 0 0 1 1.47 1.2l1.2 6.3M7.5 16.5v1.125a1.125 1.125 0 0 1-2.25 0V16.5m13.5 0v1.125a1.125 1.125 0 0 1-2.25 0V16.5M6.75 12h10.5" />
            </svg>
        </div>
    @endif
</div>

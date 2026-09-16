@props([
    'maintenance' => null,
    'event' => null,
    'size' => 'md',
])

@php
    $verified = $maintenance
        ? $maintenance->isVerified()
        : (bool) ($event['is_verified'] ?? false);
    $rootClass = $verified ? 'prov-verified' : 'prov-declared';
    $sizeClass = $size === 'sm' ? 'w-6 h-6 text-[10px]' : 'w-7 h-7 text-xs';

    $logoUrl = null;
    $labelSource = '?';

    if ($maintenance) {
        $logoUrl = $maintenance->verifiedWorkshop?->logoUrl()
            ?? $maintenance->workshop?->logoUrl();
        $labelSource = $maintenance->verifiedWorkshop?->name
            ?? $maintenance->workshop?->name
            ?? $maintenance->workshop_name
            ?? $maintenance->user?->name
            ?? '?';
    } elseif (is_array($event)) {
        $logoUrl = $event['workshop_logo_url'] ?? null;
        $labelSource = $event['workshop_name'] ?? $event['provenance_label'] ?? '?';
    }

    $initials = collect(preg_split('/\s+/', trim((string) $labelSource)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('');
@endphp

@if ($verified)
    <div {{ $attributes->class(["prov-marker prov-marker--verified {$sizeClass}", $rootClass]) }}>
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="" class="h-full w-full object-cover">
        @else
            {{ $initials }}
        @endif
    </div>
@else
    <div {{ $attributes->class(["prov-marker prov-marker--declared {$sizeClass}", $rootClass]) }}>
        {{ $initials }}
    </div>
@endif

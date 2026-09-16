@props([
    'maintenance',
    'href' => null,
])

@php
    $verified = $maintenance->isVerified();
    $rootClass = $verified ? 'prov-verified' : 'prov-declared';
    $railClass = $verified ? 'prov-rail' : 'prov-rail prov-rail--declared';
    $cardClass = $verified ? 'prov-card' : 'prov-card prov-card--declared';
    $titleClass = $verified ? 'font-semibold text-automotive-900' : 'font-medium text-automotive-700';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$cardClass, $rootClass, 'mb-3 block hover:border-wrench-300']) }}>
        <div class="{{ $railClass }} {{ $rootClass }}"></div>
        <x-provenance-marker :maintenance="$maintenance" />
        <div class="min-w-0 flex-1">
            <p class="{{ $titleClass }}">{{ $maintenance->maintenance_type }}</p>
            <p class="text-sm text-automotive-600">{{ $maintenance->provenance_card_label }}</p>
            <p class="text-xs text-automotive-500">{{ $maintenance->provenance_meta }}</p>
        </div>
    </a>
@else
    <div {{ $attributes->class([$cardClass, $rootClass, 'mb-3']) }}>
        <div class="{{ $railClass }} {{ $rootClass }}"></div>
        <x-provenance-marker :maintenance="$maintenance" />
        <div class="min-w-0 flex-1">
            <p class="{{ $titleClass }}">{{ $maintenance->maintenance_type }}</p>
            <p class="text-sm text-automotive-600">{{ $maintenance->provenance_card_label }}</p>
            <p class="text-xs text-automotive-500">{{ $maintenance->provenance_meta }}</p>
        </div>
    </div>
@endif

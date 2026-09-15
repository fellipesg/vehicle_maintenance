@props([
    'vehicle',
    'size' => 'card',
    'editRoute' => null,
])

@php
    $isHero = $size === 'hero';
    $chassisClass = $isHero
        ? 'font-mono tracking-wider font-semibold text-automotive-900 text-lg sm:text-xl'
        : 'font-mono tracking-wider font-semibold text-automotive-900 text-sm';
    $plate = $vehicle->license_plate;
    $editUrl = $editRoute ?? (auth()->check() ? route('user.vehicles.edit', $vehicle) : null);
@endphp

<div {{ $attributes->class(['space-y-1']) }}>
    <p class="text-xs uppercase tracking-wide text-automotive-500">Chassi</p>
    @if ($vehicle->chassis)
        <div class="flex flex-wrap items-center gap-2">
            <p class="{{ $chassisClass }}" data-chassis-value>{{ $vehicle->chassis }}</p>
            @if ($isHero)
                <button type="button" class="btn-secondary text-xs py-1 px-2" data-copy-chassis data-chassis="{{ $vehicle->chassis }}">
                    Copiar
                </button>
            @endif
        </div>
    @else
        @if ($editUrl)
            <a href="{{ $editUrl }}" class="inline-flex rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900">
                Chassi não informado
            </a>
        @else
            <span class="inline-flex rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900">
                Chassi não informado
            </span>
        @endif
    @endif
    @if ($plate)
        <span class="inline-flex rounded-md border border-automotive-200 px-2 py-0.5 text-xs text-automotive-700">
            Placa atual {{ $plate }}
        </span>
    @endif
</div>

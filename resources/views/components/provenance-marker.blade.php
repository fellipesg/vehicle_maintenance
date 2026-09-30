{{--
    Marcador de procedência (contrato de .ai/rules/theme.md): disco de tinta com o logo (inteiro,
    object-contain) ou as iniciais da oficina para o Selo da oficina; anel tracejado com "PR"
    (proprietário) ou "LJ" (lojista) para as declaradas, as mesmas letras da x-provenance-legend. As
    declaradas nunca mostram as iniciais do nome de quem declarou: a busca e o /v/ são vistos por
    quem não é dono.

    Props:
    - maintenance: a manutenção (usa verifiedWorkshop e workshop, se carregados, e registered_by_type).
    - event: alternativa ao maintenance, um evento de App\Services\Vehicle\VehicleTimelineBuilder.
    - size: sm (24px) | md (36px, o padrão) | lg (44px).

    É decorativo (aria-hidden="true"): quem usa o marcador escreve a procedência em texto ao lado
    ("Selo da oficina", "Declarada pelo proprietário") ou num <span class="sr-only">.
--}}
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
    $sizeClass = match ($size) {
        'sm' => 'prov-marker--sm',
        'lg' => 'prov-marker--lg',
        default => '',
    };

    $logoUrl = null;
    $labelSource = '?';
    $registeredBy = $maintenance?->registered_by_type ?? (is_array($event) ? ($event['registered_by_type'] ?? null) : null);

    if ($maintenance && $verified) {
        $logoUrl = $maintenance->verifiedWorkshop?->logoUrl()
            ?? $maintenance->workshop?->logoUrl();
        $labelSource = $maintenance->verifiedWorkshop?->name
            ?? $maintenance->workshop?->name
            ?? $maintenance->workshop_name
            ?? '?';
    } elseif (is_array($event) && $verified) {
        $logoUrl = $event['workshop_logo_url'] ?? null;
        $labelSource = $event['workshop_name'] ?? '?';
    }

    $initials = $verified
        ? collect(preg_split('/\s+/', trim((string) $labelSource)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('')
        : ($registeredBy === 'garage' ? 'LJ' : 'PR');
@endphp

@if ($verified)
    <div {{ $attributes->class(["prov-marker prov-marker--verified {$sizeClass}", $rootClass])->merge(['aria-hidden' => 'true']) }}>
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="" class="h-full w-full object-contain">
        @else
            {{ $initials }}
        @endif
    </div>
@else
    <div {{ $attributes->class(["prov-marker prov-marker--declared {$sizeClass}", $rootClass])->merge(['aria-hidden' => 'true']) }}>
        {{ $initials }}
    </div>
@endif

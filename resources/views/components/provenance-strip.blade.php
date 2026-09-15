@props([
    'vehicle',
    'maintenancePathPrefix' => '/usuario/manutencoes',
    'filterBaseUrl' => null,
])

@php
    use App\Support\VehicleProvenanceStrip;

    $strip = VehicleProvenanceStrip::segmentsForVehicle($vehicle);
    $total = (int) ($vehicle->maintenances_count ?? $vehicle->maintenances?->count() ?? count($strip));
    $verified = (int) ($vehicle->verified_maintenances_count ?? collect($strip)->where('is_verified', true)->count());
    $declared = max(0, $total - $verified);
    $baseUrl = $filterBaseUrl ?? request()->url();
    $query = request()->except('verified', 'page');
    $allUrl = $baseUrl.(count($query) ? '?'.http_build_query($query) : '');
    $verifiedUrl = $baseUrl.'?'.http_build_query([...$query, 'verified' => '1']);
    $declaredUrl = $baseUrl.'?'.http_build_query([...$query, 'verified' => '0']);
    $currentFilter = request()->query('verified');
@endphp

<div {{ $attributes->class(['mb-4']) }}>
    <div class="prov-strip" role="img" aria-label="Faixa de procedência das manutenções">
        @foreach ($strip as $segment)
            @php
                $segmentClass = $segment['is_verified'] ? 'prov-strip-segment--verified' : 'prov-strip-segment--declared';
                $title = isset($segment['date'])
                    ? \Carbon\Carbon::parse($segment['date'])->format('d/m/Y')
                    : '—';
                $href = $segment['maintenance_id']
                    ? $maintenancePathPrefix.'/'.$segment['maintenance_id']
                    : '#';
            @endphp
            <a
                href="{{ $href }}"
                class="prov-strip-segment {{ $segmentClass }}"
                title="{{ $title }}"
            ></a>
        @endforeach
    </div>
    <p class="mt-2 text-sm text-automotive-600">
        {{ $total }} manutenções · {{ $verified }} com selo de oficina · {{ $declared }} declaradas
    </p>
    <div class="mt-2 flex flex-wrap gap-3 text-sm">
        <a href="{{ $allUrl }}" @class(['underline', 'font-semibold text-automotive-900' => $currentFilter === null])>Todas</a>
        <a href="{{ $verifiedUrl }}" @class(['underline', 'font-semibold text-automotive-900' => $currentFilter === '1'])>Selo da oficina</a>
        <a href="{{ $declaredUrl }}" @class(['underline', 'font-semibold text-automotive-900' => $currentFilter === '0'])>Declaradas</a>
    </div>
</div>

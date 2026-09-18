@props([
    'vehicle',
    'maintenancePathPrefix' => '/usuario/manutencoes',
    'filterBaseUrl' => null,
    'interactiveFilters' => false,
])

@php
    use App\Support\VehicleProvenanceStrip;

    $strip = VehicleProvenanceStrip::segmentsForVehicle($vehicle);
    $total = (int) ($vehicle->maintenances_count ?? $vehicle->maintenances?->count() ?? count($strip));
    $verified = (int) ($vehicle->verified_maintenances_count ?? collect($strip)->where('is_verified', true)->count());
    $declared = max(0, $total - $verified);
    $visibleDots = array_slice($strip, 0, 16);
    $overflowDots = max(0, count($strip) - count($visibleDots));
    $rawBase = $filterBaseUrl ?? request()->url();
    $basePath = strtok((string) $rawBase, '?') ?: (string) $rawBase;
    $embeddedQuery = [];
    if (str_contains((string) $rawBase, '?')) {
        parse_str((string) parse_url($rawBase, PHP_URL_QUERY), $embeddedQuery);
    }
    $query = array_merge(
        $embeddedQuery,
        request()->except(array_merge(['verified', 'page'], array_keys($embeddedQuery))),
    );
    $allUrl = $basePath.(count($query) ? '?'.http_build_query($query) : '');
    $verifiedUrl = $basePath.'?'.http_build_query([...$query, 'verified' => '1']);
    $declaredUrl = $basePath.'?'.http_build_query([...$query, 'verified' => '0']);
    $currentFilter = request()->query('verified');
    $declaredLabel = $declared === 1 ? 'declarada' : 'declaradas';
@endphp

<div {{ $attributes->class(['mb-4']) }} data-provenance-strip>
    <p class="prov-strip-summary text-sm text-automotive-700">
        <span class="font-medium text-[#0f766e]">{{ $verified }}</span> com selo ·
        <span class="font-medium text-[#92400e]">{{ $declared }}</span> {{ $declaredLabel }}
    </p>
    @if (count($visibleDots) > 0)
        <div class="prov-dots-row" role="img" aria-label="Linha de procedência das manutenções, da mais antiga à mais recente">
            @foreach ($visibleDots as $segment)
                @php
                    $dotClass = $segment['is_verified'] ? 'prov-dot--verified' : 'prov-dot--declared';
                    $dateLabel = isset($segment['date'])
                        ? \Carbon\Carbon::parse($segment['date'])->format('d/m/Y')
                        : '—';
                    $title = ($segment['is_verified'] ? 'Selo da oficina' : 'Declarada').' · '.$dateLabel;
                    $href = $segment['maintenance_id']
                        ? $maintenancePathPrefix.'/'.$segment['maintenance_id']
                        : '#';
                @endphp
                <a href="{{ $href }}" class="prov-dot-link" title="{{ $title }}">
                    <span class="prov-dot {{ $dotClass }}"></span>
                </a>
            @endforeach
            @if ($overflowDots > 0)
                <span class="prov-dots-more" title="{{ $overflowDots }} manutenções a mais">+{{ $overflowDots }}</span>
            @endif
        </div>
    @endif
    <div class="mt-2 flex flex-wrap gap-3 text-sm">
        @if ($interactiveFilters)
            <button type="button" data-provenance-filter="" @class(['underline', 'font-semibold text-automotive-900' => $currentFilter === null])>Todas</button>
            <button type="button" data-provenance-filter="1" @class(['underline', 'font-semibold text-automotive-900' => $currentFilter === '1'])>Selo da oficina</button>
            <button type="button" data-provenance-filter="0" @class(['underline', 'font-semibold text-automotive-900' => $currentFilter === '0'])>Declaradas</button>
        @else
            <a href="{{ $allUrl }}" @class(['underline', 'font-semibold text-automotive-900' => $currentFilter === null])>Todas</a>
            <a href="{{ $verifiedUrl }}" @class(['underline', 'font-semibold text-automotive-900' => $currentFilter === '1'])>Selo da oficina</a>
            <a href="{{ $declaredUrl }}" @class(['underline', 'font-semibold text-automotive-900' => $currentFilter === '0'])>Declaradas</a>
        @endif
    </div>
</div>

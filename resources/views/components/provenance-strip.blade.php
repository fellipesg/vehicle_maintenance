{{--
    Faixa de procedência: resumo "N com selo · M declaradas" e um ponto por manutenção, da mais
    antiga à mais recente (contrato de .ai/rules/theme.md: ponto de 10px, gap de 4px, disco teal
    cheio = Selo da oficina, anel tracejado âmbar = Declarada; até 16 pontos e "+N").

    A linha é uma <ol> com um <li> por ponto. Ponto com link tem nome próprio
    (aria-label "Selo da oficina, 12/03/2024") e área de toque maior que o desenho (provenance.css);
    sem link, o texto fica em sr-only. Nada de role="img" envolvendo links.

    Props:
    - vehicle (obrigatório). Carregue provenanceStripMaintenances e loadCount de maintenances e
      verified_maintenances_count para não consultar de novo.
    - summary: mostra o texto "N com selo · M declaradas" (padrão: sim). Na ficha, os pontos ficam
      no card da linha do tempo e o resumo vai para o x-ui.stat "Procedência".
    - dot-href: link de cada ponto. Closure(int $maintenanceId): ?string, padrão com "{id}"
      ("/usuario/manutencoes/{id}", "#manutencao-{id}") ou false (sem link). Sem dot-href nem
      maintenance-path-prefix, os pontos não são links.
    - maintenance-path-prefix (legado): "#" liga ao card da página (#manutencao-{id}); outro valor
      vira "{prefixo}/{id}".
    - label: nome da lista de pontos para leitor de tela.
    - filters: mostra o filtro Todas / Selo da oficina / Declaradas aqui (legado). Na ficha, o filtro
      fica abaixo da linha do tempo (x-vehicle.detail), então o padrão é não mostrar, a não ser
      que a tela antiga passe filter-base-url ou interactive-filters.
    - filter-base-url / interactive-filters (legado): links com ?verified= ou botões
      [data-provenance-filter] para o JS.

    Ex.: <x-provenance-strip :vehicle="$vehicle" :summary="false" dot-href="/usuario/manutencoes/{id}" />
--}}
@props([
    'vehicle',
    'summary' => true,
    'dotHref' => null,
    'maintenancePathPrefix' => null,
    'label' => 'Procedência das manutenções, da mais antiga à mais recente',
    'filters' => null,
    'filterBaseUrl' => null,
    'interactiveFilters' => false,
])

@php
    use App\Support\VehicleProvenanceStrip;

    $strip = VehicleProvenanceStrip::segmentsForVehicle($vehicle);
    $total = (int) ($vehicle->maintenances_count ?? ($vehicle->relationLoaded('maintenances') ? $vehicle->maintenances->count() : count($strip)));
    $verified = (int) ($vehicle->verified_maintenances_count ?? collect($strip)->where('is_verified', true)->count());
    $declared = max(0, $total - $verified);
    $visibleDots = array_slice($strip, 0, 16);
    $overflowDots = max(0, count($strip) - count($visibleDots));
    $declaredLabel = $declared === 1 ? 'declarada' : 'declaradas';

    if ($dotHref === null && is_string($maintenancePathPrefix) && $maintenancePathPrefix !== '') {
        $dotHref = $maintenancePathPrefix === '#' ? '#manutencao-{id}' : rtrim($maintenancePathPrefix, '/').'/{id}';
    }
    $dotUrl = function (int $maintenanceId) use ($dotHref): ?string {
        $url = match (true) {
            $dotHref instanceof \Closure => $dotHref($maintenanceId),
            is_string($dotHref) && $dotHref !== '' => str_replace('{id}', (string) $maintenanceId, $dotHref),
            default => null,
        };

        return is_string($url) && $url !== '' ? $url : null;
    };

    $showsFilters = $filters ?? (filled($filterBaseUrl) || (bool) $interactiveFilters);
    $currentFilter = \App\Support\Vehicle\VehicleMaintenanceHistory::normalizeFilter(request()->query('verified'));
    $filterOptions = [];

    if ($showsFilters && (bool) $interactiveFilters) {
        $filterOptions = [
            ['value' => '', 'label' => 'Todas', 'attributes' => ['data-provenance-filter' => '']],
            ['value' => '1', 'label' => 'Selo da oficina', 'attributes' => ['data-provenance-filter' => '1']],
            ['value' => '0', 'label' => 'Declaradas', 'attributes' => ['data-provenance-filter' => '0']],
        ];
    } elseif ($showsFilters) {
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
        $filterOptions = [
            ['value' => '', 'label' => 'Todas', 'href' => $basePath.(count($query) ? '?'.http_build_query($query) : '')],
            ['value' => '1', 'label' => 'Selo da oficina', 'href' => $basePath.'?'.http_build_query([...$query, 'verified' => '1'])],
            ['value' => '0', 'label' => 'Declaradas', 'href' => $basePath.'?'.http_build_query([...$query, 'verified' => '0'])],
        ];
    }
@endphp

<div {{ $attributes->class(['mb-4']) }} data-provenance-strip>
    @if ($summary)
        <p class="prov-strip-summary text-sm text-muted-foreground">
            <span class="prov-verified font-medium text-[color:var(--prov-ink)]">{{ $verified }}</span> com selo ·
            <span class="prov-declared font-medium text-[color:var(--prov-ink)]">{{ $declared }}</span> {{ $declaredLabel }}
        </p>
    @endif
    @if (count($visibleDots) > 0)
        <ol class="prov-dots-row" aria-label="{{ $label }}" data-provenance-dots>
            @foreach ($visibleDots as $segment)
                @php
                    $dotClass = $segment['is_verified'] ? 'prov-dot--verified' : 'prov-dot--declared';
                    $dateLabel = isset($segment['date'])
                        ? \Carbon\Carbon::parse($segment['date'])->format('d/m/Y')
                        : 'sem data';
                    $dotName = ($segment['is_verified'] ? 'Selo da oficina' : 'Declarada').', '.$dateLabel;
                    $href = $segment['maintenance_id'] ? $dotUrl((int) $segment['maintenance_id']) : null;
                @endphp
                <li data-verified="{{ $segment['is_verified'] ? '1' : '0' }}">
                    @if ($href)
                        <a href="{{ $href }}" class="prov-dot-link" aria-label="{{ $dotName }}" title="{{ $dotName }}">
                            <span class="prov-dot {{ $dotClass }}" aria-hidden="true"></span>
                        </a>
                    @else
                        <span class="prov-dot {{ $dotClass }}" aria-hidden="true" title="{{ $dotName }}"></span>
                        <span class="sr-only">{{ $dotName }}</span>
                    @endif
                </li>
            @endforeach
            @if ($overflowDots > 0)
                <li class="prov-dots-more"><span aria-hidden="true">+{{ $overflowDots }}</span><span class="sr-only">Mais {{ $overflowDots }} {{ $overflowDots === 1 ? 'manutenção' : 'manutenções' }}</span></li>
            @endif
        </ol>
    @endif
    @if ($filterOptions !== [])
        <x-ui.segmented
            class="mt-3"
            label="Filtrar por procedência"
            :mode="(bool) $interactiveFilters ? 'buttons' : 'links'"
            :options="$filterOptions"
            :value="$currentFilter"
        />
    @endif
</div>

{{--
    Gráfico de barras empilhadas "Manutenções por mês: Selo da oficina × Declaradas", sem biblioteca:
    cada mês é uma coluna com um <svg> inline (Selo da oficina embaixo, Declaradas em cima, 2px de
    folga entre os dois), rótulos e grade em HTML (o texto não encolhe com o gráfico) e as cores do
    contrato de procedência (.ai/rules/theme.md): prov-verified e prov-declared.

    Acessibilidade: o desenho é um role="img" com o resumo no aria-label; os números de cada mês
    ficam na tabela de "Ver os números" (e no title de cada coluna, ao passar o mouse). A legenda
    diz o que é cada cor, então a identidade não depende só da cor.

    Props:
    - series (obrigatório): App\Support\Admin\MonthlyProvenanceSeries::lastMonths(), do mês mais
      antigo para o mais recente.
    - id: prefixo dos ids (padrão "grafico-procedencia").

    Ex.: <x-admin.provenance-chart :series="$monthlySeries" />
--}}
@props([
    'series' => [],
    'id' => 'grafico-procedencia',
])
@php
    \App\Support\UiProps::required('x-admin.provenance-chart', 'series', count($series) > 0 ? 'ok' : null, 'Passe MonthlyProvenanceSeries::lastMonths().');

    $chartMonths = collect($series)->values();
    $chartFormat = fn (int $value): string => number_format($value, 0, ',', '.');
    $chartTotal = (int) $chartMonths->sum('total');
    $chartSealed = (int) $chartMonths->sum('sealed');
    $chartDeclared = $chartTotal - $chartSealed;
    $chartSealedPercent = $chartTotal > 0 ? (int) round($chartSealed / $chartTotal * 100) : 0;
    $chartPeak = (int) $chartMonths->max('total');

    // Topo do eixo arredondado para 1, 2 ou 5 × 10^n, com três linhas de grade (topo, metade e zero).
    $chartScaleMax = 1;
    if ($chartPeak > 0) {
        $chartMagnitude = 10 ** (int) floor(log10($chartPeak));
        foreach ([1, 2, 5, 10] as $chartStep) {
            if ($chartStep * $chartMagnitude >= $chartPeak) {
                $chartScaleMax = $chartStep * $chartMagnitude;
                break;
            }
        }
    }
    $chartTicks = [$chartScaleMax, $chartScaleMax / 2, 0];
    $chartTickLabel = fn (int|float $value): string => number_format($value, fmod((float) $value, 1.0) === 0.0 ? 0 : 1, ',', '.');

    $chartFirst = $chartMonths->first();
    $chartLast = $chartMonths->last();
    $chartRange = $chartFirst && $chartLast ? $chartFirst['long_label'].' a '.$chartLast['long_label'] : '';
    $chartPeakMonth = $chartMonths->sortByDesc('total')->first();
    $chartSummary = $chartTotal === 0
        ? 'Nenhuma manutenção de '.$chartRange.'.'
        : 'Manutenções por mês de '.$chartRange.': '.$chartFormat($chartTotal).' no total, '
            .$chartFormat($chartSealed).' com Selo da oficina ('.$chartSealedPercent.'%) e '
            .$chartFormat($chartDeclared).' declaradas. Mês com mais registros: '
            .$chartPeakMonth['long_label'].', com '.$chartFormat((int) $chartPeakMonth['total']).'.';
    $chartBarHeight = fn (int $value): float => round($value / $chartScaleMax * 100, 2);
@endphp
<figure {{ $attributes->class(['min-w-0 rounded-card border border-border bg-surface p-4 sm:p-6'])->merge(['data-slot' => 'provenance-chart', 'id' => $id]) }}>
    <figcaption class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <p class="text-sm text-muted-foreground">
            <span class="font-semibold text-foreground tabular-nums">{{ $chartFormat($chartTotal) }}</span>
            {{ $chartTotal === 1 ? 'manutenção' : 'manutenções' }} em {{ $chartMonths->count() }} meses
            · <span class="font-semibold text-foreground tabular-nums">{{ $chartSealedPercent }}%</span> com Selo da oficina
        </p>
        <ul role="list" class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-foreground" data-slot="provenance-chart-legend">
            <li class="flex items-center gap-2"><span class="size-3 shrink-0 rounded-sm bg-prov-verified" aria-hidden="true"></span>Selo da oficina</li>
            <li class="flex items-center gap-2"><span class="size-3 shrink-0 rounded-sm bg-prov-declared" aria-hidden="true"></span>Declaradas</li>
        </ul>
    </figcaption>

    <div class="mt-5 grid grid-cols-[2.5rem_minmax(0,1fr)] gap-x-2" role="img" aria-label="{{ $chartSummary }}" data-slot="provenance-chart-plot">
        {{-- Eixo: os rótulos ficam na altura das linhas de grade (topo, meio e zero). --}}
        <div class="relative h-48 sm:h-56" aria-hidden="true">
            @foreach($chartTicks as $tickIndex => $tickValue)
                <span @class([
                    'absolute right-0 text-xs text-subtle-foreground tabular-nums',
                    'top-0 -translate-y-1/2' => $tickIndex === 0,
                    'top-1/2 -translate-y-1/2' => $tickIndex === 1,
                    'bottom-0 translate-y-1/2' => $tickIndex === 2,
                ])>{{ $chartTickLabel($tickValue) }}</span>
            @endforeach
        </div>

        <div class="relative h-48 sm:h-56" aria-hidden="true">
            <div class="absolute inset-x-0 top-0 border-t border-border"></div>
            <div class="absolute inset-x-0 top-1/2 border-t border-border"></div>
            <div class="absolute inset-x-0 bottom-0 border-t border-border-strong"></div>

            <div class="relative grid h-full" style="grid-template-columns: repeat({{ $chartMonths->count() }}, minmax(0, 1fr));">
                @foreach($chartMonths as $chartMonth)
                    @php
                        $sealedHeight = $chartBarHeight((int) $chartMonth['sealed']);
                        $declaredHeight = $chartBarHeight((int) $chartMonth['declared']);
                        // 2px de folga entre os segmentos (1 unidade ≈ 2px na altura do gráfico).
                        $segmentGap = $sealedHeight > 0 && $declaredHeight > 0 ? 1 : 0;
                        $declaredDrawnHeight = max(0, $declaredHeight - $segmentGap);
                    @endphp
                    <div class="flex h-full justify-center px-0.5" title="{{ $chartMonth['long_label'] }}: {{ $chartFormat((int) $chartMonth['sealed']) }} com Selo da oficina, {{ $chartFormat((int) $chartMonth['declared']) }} declaradas" data-chart-month="{{ $chartMonth['month'] }}">
                        <svg viewBox="0 0 20 100" preserveAspectRatio="none" class="h-full w-full max-w-6" focusable="false">
                            @if($sealedHeight > 0)
                                <rect x="0" y="{{ 100 - $sealedHeight }}" width="20" height="{{ $sealedHeight }}" class="fill-prov-verified" data-segment="sealed" />
                            @endif
                            @if($declaredDrawnHeight > 0)
                                <rect x="0" y="{{ 100 - $sealedHeight - $segmentGap - $declaredDrawnHeight }}" width="20" height="{{ $declaredDrawnHeight }}" class="fill-prov-declared" data-segment="declared" />
                            @endif
                        </svg>
                    </div>
                @endforeach
            </div>
        </div>

        <div aria-hidden="true"></div>
        <div class="mt-2 grid" style="grid-template-columns: repeat({{ $chartMonths->count() }}, minmax(0, 1fr));" aria-hidden="true">
            @foreach($chartMonths as $chartMonth)
                <span class="truncate text-center text-xs text-muted-foreground">{{ $chartMonth['label'] }}</span>
            @endforeach
        </div>
    </div>

    <details class="group mt-4 text-sm" data-slot="provenance-chart-table">
        <summary class="inline-flex min-h-10 cursor-pointer list-none items-center gap-1.5 rounded-control font-medium text-link hover:text-link-hover [&::-webkit-details-marker]:hidden">
            <x-ui.icon name="chevron-down" class="size-4 transition-transform duration-fast ease-smooth-out group-open:rotate-180 motion-reduce:transition-none" />
            Ver os números
        </summary>
        <div class="mt-3">
            <x-ui.table caption="Manutenções por mês e procedência">
                <x-slot:head>
                    <tr>
                        <th>Mês</th>
                        <th class="text-right">Selo da oficina</th>
                        <th class="text-right">Declaradas</th>
                        <th class="text-right">Total</th>
                    </tr>
                </x-slot:head>
                @foreach($chartMonths as $chartMonth)
                    <tr>
                        <th scope="row" class="font-medium">{{ $chartMonth['long_label'] }}</th>
                        <td class="text-right">{{ $chartFormat((int) $chartMonth['sealed']) }}</td>
                        <td class="text-right">{{ $chartFormat((int) $chartMonth['declared']) }}</td>
                        <td class="text-right">{{ $chartFormat((int) $chartMonth['total']) }}</td>
                    </tr>
                @endforeach
                <x-slot:foot>
                    <tr>
                        <th scope="row">Total</th>
                        <td class="text-right">{{ $chartFormat($chartSealed) }}</td>
                        <td class="text-right">{{ $chartFormat($chartDeclared) }}</td>
                        <td class="text-right">{{ $chartFormat($chartTotal) }}</td>
                    </tr>
                </x-slot:foot>
            </x-ui.table>
        </div>
    </details>
</figure>

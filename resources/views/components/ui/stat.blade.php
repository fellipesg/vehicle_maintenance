{{--
    Indicador (KPI) em card: rótulo, valor e dica. Substitui .stat-card com "!p-4".

    Props:
    - label: o que é medido ("Veículos", "Manutenções no ano"). Obrigatório.
    - value: o número já formatado (ex.: number_format). Obrigatório (0 vale). Sai em tabular-nums. Precisa vir de um total
      real (count() ou meta.total), nunca da contagem de uma lista limitada. Sem contagem animada.
    - hint: texto pequeno abaixo do valor ("Última em 12/03/2026").
    - icon: ícone decorativo no canto.
    - href: o card inteiro vira link (hover levanta 2px com motion-safe); o nome do link é o texto
      do card ("Veículos 12 ...").
    - trend: up | down | neutral. Mostra seta e o texto de trend-label, com um prefixo só para
      leitor de tela ("Aumento:", "Queda:", "Estável:"), para não depender só da cor.
    - trend-label: o texto da variação ("+3 este mês"). Obrigatório quando há trend.
    - trend-tone: positive | negative | neutral. Padrão: up = positive (verde), down = negative
      (vermelho). Use para inverter quando subir é ruim (custo, atraso).

    Slot: conteúdo extra abaixo da dica (opcional).

    Ex.: <x-ui.stat label="Veículos" :value="$vehiclesCount" icon="truck" :href="route('user.vehicles.index')" />
         <x-ui.stat label="Gasto no ano" value="R$ 4.320,00" trend="up" trend-label="+12% sobre 2025" trend-tone="negative" />
--}}
@props([
    'label' => null,
    'value' => null,
    'hint' => null,
    'icon' => null,
    'href' => null,
    'trend' => null,
    'trendLabel' => null,
    'trendTone' => null,
])
@php
    \App\Support\UiProps::required('x-ui.stat', 'label', $label);
    \App\Support\UiProps::required('x-ui.stat', 'value', $value, 'Zero é valor: passe :value="0".');

    $statIsLink = filled($href);
    $statTrend = filled($trend) ? \App\Support\UiProps::oneOf('x-ui.stat', 'trend', $trend, ['up', 'down', 'neutral'], 'neutral') : null;

    if ($statTrend !== null) {
        \App\Support\UiProps::required('x-ui.stat', 'trend-label quando tem trend', $trendLabel, 'A seta sozinha não diz quanto mudou.');
    }

    $statTrendTone = $statTrend === null ? null : \App\Support\UiProps::oneOf(
        'x-ui.stat',
        'trend-tone',
        $trendTone ?? match ($statTrend) {
            'up' => 'positive',
            'down' => 'negative',
            'neutral' => 'neutral',
        },
        ['positive', 'negative', 'neutral'],
        'neutral',
    );
    $statTrendIcon = match ($statTrend) {
        'up' => 'chevron-up',
        'down' => 'chevron-down',
        default => 'minus',
    };
    $statTrendPrefix = match ($statTrend) {
        'up' => 'Aumento:',
        'down' => 'Queda:',
        default => 'Estável:',
    };
    $statTrendClass = match ($statTrendTone) {
        'positive' => 'text-success',
        'negative' => 'text-danger',
        default => 'text-muted-foreground',
    };
@endphp
<{{ $statIsLink ? 'a' : 'div' }} {{ $attributes->class([
    'block min-w-0 rounded-card border border-border bg-surface p-4 text-foreground shadow-sm',
    'transition-[border-color,box-shadow,translate] duration-fast ease-smooth-out motion-reduce:transition-none hover:border-accent-border hover:shadow-md motion-safe:hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring' => $statIsLink,
])->merge(['data-slot' => 'stat', 'href' => $statIsLink ? $href : null]) }}>
    <div class="flex items-start justify-between gap-3">
        <dl class="min-w-0">
            <dt data-slot="stat-label" class="text-sm font-medium text-muted-foreground">{{ $label }}</dt>
            <dd data-slot="stat-value" class="mt-1 text-2xl font-semibold tracking-tight text-foreground tabular-nums">{{ $value }}</dd>
        </dl>
        @if(filled($icon))
            <span data-slot="stat-icon" class="inline-flex size-9 shrink-0 items-center justify-center rounded-control bg-accent text-accent-foreground">
                <x-ui.icon :name="$icon" />
            </span>
        @endif
    </div>
    @if($statTrend !== null)
        <p data-slot="stat-trend" class="mt-2 inline-flex items-center gap-1 text-xs font-medium tabular-nums {{ $statTrendClass }}">
            <x-ui.icon :name="$statTrendIcon" variant="solid" class="size-4" />
            <span><span class="sr-only">{{ $statTrendPrefix }} </span>{{ $trendLabel }}</span>
        </p>
    @endif
    @if(filled($hint))
        <p data-slot="stat-hint" class="mt-1 text-xs text-subtle-foreground">{{ $hint }}</p>
    @endif
    @if($slot->hasActualContent())
        <div class="mt-3">{{ $slot }}</div>
    @endif
</{{ $statIsLink ? 'a' : 'div' }}>

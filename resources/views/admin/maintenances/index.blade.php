@extends('layouts.admin')

@section('title', 'Manutenções')

@php
    $adminBreadcrumbs = [['Frota'], ['Manutenções']];
    $currentVerified = $verified ?? '';
    $provenanceFilters = collect(['' => 'Todas', '1' => 'Selo da oficina', '0' => 'Declaradas'])
        ->map(fn (string $filterLabel, int|string $filterValue): array => [
            'value' => (string) $filterValue,
            'label' => $filterLabel,
            'count' => $counts[(string) $filterValue] ?? 0,
            'attributes' => ['data-maintenance-filter' => (string) $filterValue],
        ])
        ->values()
        ->all();
    // Contexto vindo de outras telas (conta, veículo, oficina) e a ordenação seguem nos envios do formulário.
    $keptParams = collect(request()->only(['usuario', 'veiculo', 'oficina', 'ordenar', 'direcao']))
        ->filter(fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')
        ->all();
    $hasTextFilters = $filters['q'] !== '' || $filters['from'] !== null || $filters['to'] !== null;
    $hasAnyFilter = $hasTextFilters || $currentVerified !== '' || $contextFilters !== [];
@endphp

@section('content')
    <div
        data-admin-maintenances
        data-base-url="{{ route('admin.maintenances.index') }}"
        data-verified="{{ $currentVerified }}"
    >
        <x-ui.page-header title="Manutenções" description="Histórico de manutenções de toda a plataforma, com a procedência de cada registro." />

        <form
            method="GET"
            action="{{ route('admin.maintenances.index') }}"
            id="filtros-manutencoes"
            role="search"
            aria-label="Filtrar manutenções"
            class="mb-4 space-y-4 rounded-card border border-border bg-surface p-4"
            data-admin-maintenances-form
            data-submit-busy="off"
        >
            @foreach($keptParams as $paramName => $paramValue)
                <input type="hidden" name="{{ $paramName }}" value="{{ $paramValue }}">
            @endforeach

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_11rem_11rem_auto] lg:items-end">
                <x-ui.field name="q" label="Placa, veículo, oficina ou serviço" class="sm:col-span-2 lg:col-span-1">
                    <x-ui.input type="search" :value="$filters['q']" leading-icon="magnifying-glass" autocomplete="off" placeholder="Ex.: ABC1D23 ou troca de óleo" />
                </x-ui.field>
                <x-ui.field name="de" label="De">
                    <x-ui.input type="date" :value="$filters['from']?->format('Y-m-d')" />
                </x-ui.field>
                <x-ui.field name="ate" label="Até">
                    <x-ui.input type="date" :value="$filters['to']?->format('Y-m-d')" />
                </x-ui.field>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <x-ui.button type="submit" variant="secondary" icon="funnel">Filtrar</x-ui.button>
                    @if($hasAnyFilter)
                        <x-ui.button variant="ghost" :href="route('admin.maintenances.index')">Limpar filtros</x-ui.button>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-border pt-4 lg:flex-row lg:items-center lg:justify-between">
                {{--
                    A procedência escolhida vai no campo oculto quando o envio é pelo "Filtrar"; cada
                    botão do segmentado também envia o próprio valor. Com JS, o clique troca a lista no
                    lugar (resources/js/admin-maintenances-filters.js).
                --}}
                <input type="hidden" name="verified" value="{{ $currentVerified }}" data-admin-maintenances-verified @disabled($currentVerified === '')>
                <x-ui.segmented mode="buttons" name="verified" label="Filtrar por procedência" controls="admin-maintenances-results" :options="$provenanceFilters" :value="$currentVerified" />
                <x-provenance-legend />
            </div>
        </form>

        @if($contextFilters !== [])
            <ul role="list" class="mb-4 flex flex-wrap items-center gap-2" aria-label="Filtros aplicados" data-admin-maintenances-context>
                @foreach($contextFilters as $contextFilter)
                    <li>
                        <a
                            href="{{ route('admin.maintenances.index', collect(request()->query())->except([$contextFilter['param'], 'page'])->all()) }}"
                            class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-border-strong bg-surface px-3 text-sm text-foreground transition-colors duration-fast ease-smooth-out hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                        >
                            {{ $contextFilter['label'] }}
                            <x-ui.icon name="x-mark" class="size-4 text-muted-foreground" />
                            <span class="sr-only">(remover filtro)</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-admin-maintenances-status></p>

        <div id="admin-maintenances-results" class="transition-opacity duration-fast ease-smooth-out motion-reduce:transition-none" data-admin-maintenances-results aria-busy="false">
            @include('admin.maintenances._results')
        </div>

        <template data-admin-maintenances-error-template>
            <x-ui.alert variant="danger" title="Não foi possível carregar as manutenções.">
                Confira sua conexão e tente de novo.
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm" icon="arrow-path" data-admin-maintenances-retry>Tentar novamente</x-ui.button>
                </x-slot:actions>
            </x-ui.alert>
        </template>
    </div>
@endsection

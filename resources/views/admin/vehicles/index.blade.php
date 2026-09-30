@extends('layouts.admin')

@section('title', 'Veículos')

@php
    $adminBreadcrumbs = [['Frota'], ['Veículos']];
    $hasSearch = $search !== '';
    $formatCount = fn (int $count): string => number_format($count, 0, ',', '.');
@endphp

@section('content')
    <x-ui.page-header title="Veículos" description="Frota completa da plataforma, com o proprietário atual e a última oficina de cada veículo.">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.vehicles.index') }}" class="flex w-full gap-2 sm:w-auto" role="search" aria-label="Buscar veículo" data-submit-busy="off">
                @if(request()->query('ordenar'))
                    <input type="hidden" name="ordenar" value="{{ $sort }}">
                    <input type="hidden" name="direcao" value="{{ $direction }}">
                @endif
                <label for="admin-vehicle-search" class="sr-only">Buscar veículo por chassi, placa ou RENAVAM</label>
                <x-ui.input
                    type="search"
                    id="admin-vehicle-search"
                    name="search"
                    :value="$search"
                    placeholder="Placa, chassi ou RENAVAM"
                    leading-icon="magnifying-glass"
                    autocomplete="off"
                    class="min-w-0 flex-1 sm:w-72"
                />
                <x-ui.button type="submit" variant="secondary">Buscar</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <p class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground" @if($hasSearch) data-admin-vehicle-search-summary @endif>
        @if($hasSearch)
            <span>
                {{ $formatCount($vehicles->total()) }} {{ $vehicles->total() === 1 ? 'resultado' : 'resultados' }} para
                <span class="font-medium text-foreground">“{{ $search }}”</span>
            </span>
            <x-ui.link :href="route('admin.vehicles.index')" icon="x-mark">Limpar busca</x-ui.link>
        @else
            <span>{{ $formatCount($vehicles->total()) }} {{ $vehicles->total() === 1 ? 'veículo' : 'veículos' }}</span>
        @endif
    </p>

    <x-ui.table caption="Veículos da plataforma" stack :sort="$sort" :direction="$direction">
        <x-slot:head>
            <tr>
                <th class="w-20"><span class="sr-only">Capa</span></th>
                <th class="min-w-40" data-sort="veiculo">Veículo</th>
                <th>Placa</th>
                <th>Chassi</th>
                <th class="min-w-44">Proprietário atual</th>
                <th class="min-w-44">Última oficina</th>
                <th class="text-right" data-sort="manutencoes" data-sort-default="desc">Manutenções</th>
                <th data-sort="cadastro" data-sort-default="desc">Cadastro</th>
                <th class="text-right"><span class="sr-only">Ações</span></th>
            </tr>
        </x-slot:head>

        @foreach($vehicles as $vehicle)
            @php
                $owner = $vehicle->owners->first();
                $latest = $vehicle->maintenances->first();
                $workshopLabel = $latest?->workshop?->name ?? $latest?->workshop_name;
                $vehicleName = trim($vehicle->brand.' '.$vehicle->model);
            @endphp
            <tr>
                <td class="py-2 max-md:hidden">
                    <x-vehicle-cover :vehicle="$vehicle" />
                </td>
                <th scope="row" class="font-medium">
                    <x-ui.link :href="route('admin.vehicles.show', $vehicle)">{{ $vehicleName }}</x-ui.link>
                    @if($vehicle->year)
                        <span class="block text-xs font-normal text-muted-foreground">{{ $vehicle->year }}</span>
                    @endif
                </th>
                <td class="font-mono whitespace-nowrap tracking-wider">{{ $vehicle->license_plate ?? '—' }}</td>
                <td class="font-mono text-xs break-all text-muted-foreground">{{ $vehicle->chassis ?? '—' }}</td>
                <td>
                    @if($owner)
                        <x-ui.link :href="route('admin.users.show', $owner)">{{ $owner->name }}</x-ui.link>
                    @else
                        <span class="text-muted-foreground">Sem proprietário atual</span>
                    @endif
                </td>
                <td class="text-muted-foreground">{{ $workshopLabel ?? '—' }}</td>
                <td class="text-right">{{ $formatCount((int) $vehicle->maintenances_count) }}</td>
                <td class="whitespace-nowrap">{{ $vehicle->created_at?->format('d/m/Y') ?? '—' }}</td>
                <td class="py-2 text-right">
                    <x-admin.row-actions :label="'Ações para o veículo '.$vehicleName.($vehicle->license_plate ? ' '.$vehicle->license_plate : '')" :id="'veiculo-'.$vehicle->id.'-acoes'">
                        <x-ui.dropdown-item :href="route('admin.vehicles.show', $vehicle)" icon="eye">Abrir veículo</x-ui.dropdown-item>
                        @if($vehicle->maintenances_count > 0)
                            <x-ui.dropdown-item :href="route('admin.maintenances.index', ['veiculo' => $vehicle->id])" icon="wrench-screwdriver">Ver manutenções</x-ui.dropdown-item>
                        @endif
                        @if($owner)
                            <x-ui.dropdown-item :href="route('admin.users.show', $owner)" icon="user-circle">Abrir proprietário atual</x-ui.dropdown-item>
                        @endif
                    </x-admin.row-actions>
                </td>
            </tr>
        @endforeach

        <x-slot:empty>
            @if($hasSearch)
                <x-ui.empty-state
                    icon="magnifying-glass"
                    title="Nenhum veículo encontrado"
                    description="Confira a placa, o chassi ou o RENAVAM e busque de novo."
                    variant="plain"
                    size="sm"
                    heading-level="p"
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" :href="route('admin.vehicles.index')">Limpar busca</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="truck"
                    title="Nenhum veículo cadastrado"
                    description="Os veículos aparecem aqui quando proprietários, lojistas ou oficinas os cadastram."
                    variant="plain"
                    size="sm"
                    heading-level="p"
                />
            @endif
        </x-slot:empty>
    </x-ui.table>

    <div class="mt-4">
        {{ $vehicles->links() }}
    </div>
@endsection

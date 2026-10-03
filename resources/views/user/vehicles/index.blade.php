{{--
    Meus veículos, renderizado no servidor: um x-vehicle.card por veículo (o card inteiro abre a
    ficha; capa inteira, placa, km e os pontos de procedência) com a próxima revisão estimada e o
    menu "Mais ações". Sem veículo, o estado vazio leva ao assistente "Adicionar veículo".
--}}
@extends('layouts.app')

@section('title', 'Meus veículos')

@php
    $vehiclesKm = fn (int $kilometers): string => number_format($kilometers, 0, ',', '.').' km';
@endphp

@section('content')
    <x-ui.container padded data-owner-page="vehicles">
        <x-ui.page-header
            title="Meus veículos"
            description="Os veículos no seu nome, com o histórico de procedência de cada um."
            :breadcrumbs="[['Início', route('user.dashboard')], ['Meus veículos']]"
        >
            <x-slot:actions>
                <x-ui.button icon="plus" :href="route('user.vehicles.create')">Adicionar veículo</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($vehicles->isEmpty())
            <x-ui.empty-state
                icon="truck"
                heading-level="h2"
                title="Nenhum veículo ainda"
                description="Adicione o primeiro pelo CRLV-e: os dados vêm do documento e o histórico fica ligado ao chassi."
            >
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('user.vehicles.create')">Adicionar veículo</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <p class="mb-4 text-sm text-muted-foreground" data-vehicles-count>
                {{ $vehicles->total() === 1 ? '1 veículo' : number_format($vehicles->total(), 0, ',', '.').' veículos' }}
            </p>

            <ul role="list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-owner-vehicles>
                @foreach ($vehicles as $vehicle)
                    @php
                        $vehicleName = trim($vehicle->brand.' '.$vehicle->model);
                        $vehicleRevision = $revisions[$vehicle->id] ?? null;
                    @endphp
                    <x-vehicle.card
                        :vehicle="$vehicle"
                        :href="route('user.vehicles.show', $vehicle)"
                        as="li"
                        heading-level="h2"
                        :add-cover-url="route('user.vehicles.edit', $vehicle).'#capas'"
                    >
                        @if ($vehicleRevision !== null && $vehicleRevision['next_due_kilometers'] !== null)
                            @if ($vehicleRevision['is_overdue'])
                                <x-ui.badge variant="danger" dot>Revisão em atraso</x-ui.badge>
                            @else
                                <p class="text-muted-foreground tabular-nums" data-next-revision>
                                    Próxima revisão aos {{ $vehiclesKm((int) $vehicleRevision['next_due_kilometers']) }}
                                    <span class="whitespace-nowrap">· faltam {{ $vehiclesKm((int) $vehicleRevision['kilometers_remaining']) }}</span>
                                </p>
                            @endif
                        @endif

                        <x-slot:actions>
                            <x-ui.dropdown :label="'Mais ações: '.$vehicleName" placement="bottom-start">
                                <x-slot:trigger class="inline-flex min-h-10 items-center gap-1.5 rounded-control border border-border-strong bg-surface px-3 text-sm font-medium text-foreground transition-colors duration-fast hover:bg-surface-muted motion-reduce:transition-none">
                                    <x-ui.icon name="ellipsis-horizontal" class="size-5" />
                                    <span>Mais ações</span>
                                </x-slot:trigger>
                                <x-ui.dropdown-item :href="route('user.maintenances.create', ['vehicle_id' => $vehicle->id])" icon="wrench-screwdriver">Registrar manutenção</x-ui.dropdown-item>
                                <x-ui.dropdown-item :href="route('user.vehicles.edit', $vehicle)" icon="pencil-square">Editar dados e capas</x-ui.dropdown-item>
                                <x-ui.dropdown-item :href="route('user.vehicles.show', $vehicle).'#documentos'" icon="document-text">Documentos</x-ui.dropdown-item>
                            </x-ui.dropdown>
                        </x-slot:actions>
                    </x-vehicle.card>
                @endforeach
            </ul>

            @if ($vehicles->hasPages())
                <div class="mt-6">{{ $vehicles->links() }}</div>
            @endif
        @endif
    </x-ui.container>
@endsection

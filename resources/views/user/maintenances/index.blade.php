{{--
    Manutenções do proprietário, renderizadas no servidor: todas as dos seus veículos em
    <x-maintenance.list> agrupado por mês, com os cards de procedência (serviço, data, veículo, km,
    oficina e Selo da oficina / Declarada), a mesma apresentação do Lojista. Filtros na query string
    (veículo e procedência: Todas / Selo da oficina / Declaradas, com as contagens) e paginação.
--}}
@extends('layouts.app')

@section('title', 'Manutenções')

@php
    use App\Enums\Portal;

    // O filtro por veículo só ajuda com mais de um veículo (ou quando já está aplicado).
    $listVehicles = $vehicles->count() > 1 || filled(request()->query('veiculo')) ? $vehicles : null;
@endphp

@section('content')
    <x-ui.container padded data-owner-page="maintenances">
        <x-ui.page-header
            title="Manutenções"
            description="O histórico dos seus veículos, com a procedência de cada registro."
            :breadcrumbs="[['Início', route('user.dashboard')], ['Manutenções']]"
        >
            <x-slot:actions>
                <x-ui.button icon="plus" :href="route('user.maintenances.create')">Registrar manutenção</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-maintenance.list
            :maintenances="$maintenances"
            :portal="Portal::Owner"
            :vehicles="$listVehicles"
            :counts="$counts"
            group-by-month
            heading-level="h2"
            caption="Manutenções dos seus veículos"
            :empty-title="$hasVehicles ? 'Nenhuma manutenção registrada' : 'Nenhum veículo ainda'"
            :empty-description="$hasVehicles
                ? 'Registre a primeira com a data, a quilometragem e a nota fiscal, se tiver. Os serviços feitos por oficinas da rede aparecem aqui com o Selo da oficina.'
                : 'Adicione um veículo pelo CRLV-e para começar o histórico de manutenções.'"
        >
            <x-slot:empty-actions>
                @if ($hasVehicles)
                    <x-ui.button icon="plus" :href="route('user.maintenances.create')">Registrar manutenção</x-ui.button>
                @else
                    <x-ui.button icon="plus" :href="route('user.vehicles.create')">Adicionar veículo</x-ui.button>
                @endif
            </x-slot:empty-actions>
        </x-maintenance.list>

        @if ($maintenances->total() > 0)
            <x-provenance-legend class="mt-8 border-t border-border pt-4" />
        @endif
    </x-ui.container>
@endsection

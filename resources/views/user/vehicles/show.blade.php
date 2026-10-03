{{--
    Ficha do veículo do proprietário, renderizada no servidor com <x-vehicle.detail> (a mesma dos
    outros portais): capa, resumo, Linha do tempo | Histórico | Documentos.

    Ações do cabeçalho (o primário por último):
    - Exportar PDF: formulário que, sem JS, pede o PDF por e-mail (user.vehicles.export-pdf). Com JS,
      resources/js/user-portal.js gera o PDF pela API, mostra o progresso (spinner, role="status") e
      troca o botão por um link "Baixar PDF" relativo, terminado em .pdf e sem o atributo download
      (.ai/rules/js.md).
    - Editar e Registrar manutenção, só para o dono atual (VehiclePolicy).
--}}
@extends('layouts.app')

@section('title', trim($vehicle->brand.' '.$vehicle->model))

@php
    use App\Enums\Portal;

    $vehicleName = trim($vehicle->brand.' '.$vehicle->model);
    $vehicleEditUrl = $canEdit ? route('user.vehicles.edit', $vehicle) : null;
    $vehicleCreateMaintenanceUrl = route('user.maintenances.create', ['vehicle_id' => $vehicle->id]);
@endphp

@section('content')
    <x-ui.container padded data-owner-page="vehicle">
        <x-vehicle.detail
            :vehicle="$vehicle"
            :portal="Portal::Owner"
            :breadcrumbs="[['Meus veículos', route('user.vehicles.index')], [$vehicleName]]"
            :edit-url="$vehicleEditUrl"
            :masked="$identifiersMasked"
        >
            <x-slot:actions>
                @if ($canExportPdf)
                    <form
                        method="POST"
                        action="{{ route('user.vehicles.export-pdf', $vehicle) }}"
                        class="contents"
                        data-vehicle-pdf-export
                        data-vehicle-id="{{ $vehicle->id }}"
                        data-submit-busy="off"
                    >
                        @csrf
                        <x-ui.button type="submit" variant="secondary" icon="document-arrow-down" class="max-sm:grow" data-pdf-export-button>Exportar PDF</x-ui.button>
                        <p class="sr-only" role="status" aria-live="polite" data-pdf-export-status></p>
                    </form>
                @endif
                @if ($canEdit)
                    <x-ui.button variant="secondary" icon="pencil-square" :href="$vehicleEditUrl">Editar</x-ui.button>
                @endif
                @if ($canAddMaintenance)
                    <x-ui.button icon="plus" :href="$vehicleCreateMaintenanceUrl">Registrar manutenção</x-ui.button>
                @endif
            </x-slot:actions>

            @unless ($vehicle->hasVerifiedOwnership())
                <x-slot:notice>
                    <x-ui.alert variant="warning" title="Propriedade não confirmada" data-ownership-unverified>
                        <p>
                            Este veículo foi cadastrado sem o CRLV-e, então ninguém confirmou que ele é seu. Enquanto
                            isso, ele não aparece na consulta de procedência e o PDF do histórico sai marcado como
                            não confirmado.
                        </p>
                        <x-ui.link :href="route('user.vehicles.create')" class="mt-2 text-sm" arrow data-ownership-verify>
                            Enviar o CRLV-e e confirmar a propriedade
                        </x-ui.link>
                    </x-ui.alert>
                </x-slot:notice>
            @endunless

            @if ($canAddMaintenance)
                <x-slot:empty-actions>
                    <x-ui.button icon="plus" :href="$vehicleCreateMaintenanceUrl">Registrar manutenção</x-ui.button>
                </x-slot:empty-actions>
            @endif
        </x-vehicle.detail>
    </x-ui.container>
@endsection

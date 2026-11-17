{{--
    Correção do próprio laudo. Só a oficina emissora abre esta tela (VehicleInspectionPolicy::update)
    e o veículo não muda: laudo não troca de carro, como a OS não troca.

    Gravar um campo que não é veredito, achado nem hodômetro não faz o serviço reavaliar as garantias
    do laudo (VehicleInspectionService::assertCoherent): corrigir a data de um laudo antigo não exige
    "arrumar" um hodômetro que não está mexendo.
--}}
@extends('layouts.app')

@section('title', 'Editar laudo')

@php
    $formErrorTargets = [
        'report' => 'report',
        'findings.*' => null,
    ];
@endphp

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Editar laudo"
            :description="'Laudo '.$inspection->verification_code"
            :breadcrumbs="[['Vistorias', route('workshop.inspections.index')], [$inspection->verification_code, route('workshop.inspections.show', $inspection)], ['Editar']]"
        />

        <div class="space-y-6">
            <x-ui.form-errors :ids="$formErrorTargets" />

            <x-ui.alert variant="info" title="Este laudo já tem código de conferência ({{ $inspection->verification_code }})" data-issued-edit-notice>
                As correções aparecem para quem abrir o laudo pelo código. O código não muda.
            </x-ui.alert>

            @include('workshop._vehicle-step', [
                'context' => 'inspection',
                'vehicle' => $vehicle,
                'licensePlate' => $vehicle?->license_plate ?? '',
                'lookup' => null,
                'vehicleHasOwner' => true,
                // A edição só abre para a oficina que emitiu o laudo: o vínculo com o veículo existe.
                'showsFullVehicle' => true,
                'readonly' => true,
            ])

            <form method="POST" action="{{ route('workshop.inspections.update', $inspection) }}" enctype="multipart/form-data" class="space-y-6" data-inspection-form>
                @csrf
                @method('PUT')

                @if(session()->hasOldInput() && $errors->any())
                    <x-ui.alert variant="warning" role="status" title="Escolha o arquivo de novo">
                        O navegador não guarda arquivos depois de um erro: anexe de novo o PDF do laudo antes de salvar. O que já estava no laudo continua.
                    </x-ui.alert>
                @endif

                @include('workshop.inspections._form', ['inspection' => $inspection])

                <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:-mx-6 sm:px-6" data-slot="form-actions">
                    <x-ui.button variant="secondary" :href="route('workshop.inspections.show', $inspection)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar laudo</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.container>
@endsection

@extends('layouts.app')

@section('title', 'Nova OS')

@php
    $formErrorTargets = [
        'license_plate' => 'license_plate',
        'photo' => null,
        'invoices*' => 'invoices',
        'photos.vehicle_before*' => 'photos_vehicle_before',
        'photos.vehicle_after*' => 'photos_vehicle_after',
        'photos.part_before*' => 'photos_part_before',
        'photos.part_after*' => 'photos_part_after',
    ];
    $showsForm = $workshop && $vehicle && $vehicleHasOwner;
    // Erro de placa, de foto ou de um grupo de fotos fica longe do topo: o resumo aparece já com um.
    $summaryThreshold = collect($errors->keys())->contains(fn (string $key): bool => in_array($key, ['license_plate', 'photo'], true) || str_starts_with($key, 'photos.')) ? 1 : 2;
@endphp

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Nova ordem de serviço"
            description="Confirme o veículo pela placa e registre o serviço. A OS recebe o Selo da oficina, que o cliente confere pelo código."
            :breadcrumbs="[['Ordens de serviço', route('workshop.maintenances.index')], ['Nova OS']]"
        />

        @if(! $workshop)
            <x-ui.empty-state icon="building-storefront" title="Cadastre sua oficina antes de registrar OS"
                              description="O nome e a logo da oficina aparecem no Selo da oficina de cada OS.">
                <x-slot:actions>
                    <x-ui.button :href="route('workshop.profile.create')">Cadastrar oficina</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <div class="space-y-6">
                <x-ui.form-errors :ids="$formErrorTargets" :threshold="$summaryThreshold" />

                @include('workshop.maintenances._vehicle-step', ['readonly' => false])

                @if($showsForm)
                    <form method="POST" action="{{ route('workshop.maintenances.store') }}" enctype="multipart/form-data" class="space-y-6" data-maintenance-os-form>
                        @csrf
                        <input type="hidden" name="license_plate" value="{{ $vehicle->license_plate }}">

                        @if(session()->hasOldInput() && $errors->any())
                            <x-ui.alert variant="warning" role="status" title="Escolha os arquivos de novo">
                                O navegador não guarda arquivos depois de um erro: anexe de novo as notas fiscais e as fotos antes de enviar.
                            </x-ui.alert>
                        @endif

                        @include('partials.workshop-maintenance-form', [
                            'maintenance' => null,
                            'vehicle' => $vehicle,
                            'existingPhotos' => collect(),
                            'editable' => false,
                        ])

                        <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:-mx-6 sm:px-6" data-slot="form-actions">
                            <x-ui.button variant="secondary" :href="route('workshop.maintenances.index')">Cancelar</x-ui.button>
                            <x-ui.button type="submit" icon="check" loading-label="Enviando fotos e notas…">Registrar OS</x-ui.button>
                        </div>
                    </form>
                @endif
            </div>
        @endif
    </x-ui.container>
@endsection

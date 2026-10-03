@extends('layouts.app')

@section('title', 'Editar OS')

@php
    $existingPhotos = $maintenance->photos->groupBy(fn ($photo) => $photo->subject.'_'.$photo->stage);
    $vehicle = $maintenance->vehicle;
    $orderLabel = $maintenance->maintenance_type.($vehicle?->license_plate ? ' · '.$vehicle->license_plate : '');
    $formErrorTargets = [
        'photo' => null,
        'invoices*' => 'invoices',
        'photos.vehicle_before*' => 'photos_vehicle_before',
        'photos.vehicle_after*' => 'photos_vehicle_after',
        'photos.part_before*' => 'photos_part_before',
        'photos.part_after*' => 'photos_part_after',
    ];
    // Erro de foto ou de um grupo de fotos fica longe do topo: o resumo aparece já com um.
    $summaryThreshold = collect($errors->keys())->contains(fn (string $key): bool => $key === 'photo' || str_starts_with($key, 'photos.')) ? 1 : 2;
@endphp

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Editar ordem de serviço"
            :description="$orderLabel"
            :breadcrumbs="[
                ['Ordens de serviço', route('workshop.maintenances.index')],
                [$orderLabel, route('workshop.maintenances.show', $maintenance)],
                ['Editar'],
            ]"
        />

        <div class="space-y-6">
            <x-ui.form-errors :ids="$formErrorTargets" :threshold="$summaryThreshold" />

            @if($maintenance->isVerified())
                <x-ui.alert variant="info" title="Esta OS tem Selo da oficina ({{ $maintenance->verification_code }})" data-sealed-edit-notice>
                    As alterações aparecem para o proprietário, na ficha do veículo e na página de verificação do selo.
                </x-ui.alert>
            @endif

            @include('workshop.maintenances._vehicle-step', [
                'vehicle' => $vehicle,
                'licensePlate' => $vehicle?->license_plate ?? '',
                'lookup' => null,
                'vehicleHasOwner' => true,
                'readonly' => true,
            ])

            <form method="POST" action="{{ route('workshop.maintenances.update', $maintenance) }}" enctype="multipart/form-data" class="space-y-6" data-maintenance-os-form>
                @csrf
                @method('PUT')

                @if(session()->hasOldInput() && $errors->any())
                    <x-ui.alert variant="warning" role="status" title="Escolha os arquivos de novo">
                        O navegador não guarda arquivos depois de um erro: anexe de novo as notas fiscais e as fotos novas antes de salvar. As que já estavam na OS continuam.
                    </x-ui.alert>
                @endif

                @include('partials.workshop-maintenance-form', [
                    'maintenance' => $maintenance,
                    'vehicle' => $vehicle,
                    'existingPhotos' => $existingPhotos,
                    'editable' => true,
                ])

                <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:-mx-6 sm:px-6" data-slot="form-actions">
                    <x-ui.button variant="secondary" :href="route('workshop.maintenances.show', $maintenance)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar alterações</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.container>
@endsection

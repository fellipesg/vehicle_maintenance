{{--
    Passo 3 (opcional) do assistente, Capas: paisagem 16:9 e retrato 9:16 recortadas no navegador
    antes do envio (.ai/rules/user-vehicles.md), com a capa atual inteira quando o veículo já tinha
    uma (vínculo). "Pular por agora" leva à ficha sem enviar nada.
--}}
@extends('vehicles.entry.layout')

@php
    $coversVehicleName = trim($vehicle->brand.' '.$vehicle->model);
    $entryStep = \App\Support\Vehicle\VehicleEntryFlow::STEP_COVERS;
    $entryTitle = 'Adicionar capas';
    $entryDescription = $coversVehicleName.' · '.$vehicle->license_plate.'. Opcional: a capa paisagem aparece em telas largas; a retrato, no celular, nos avatares e no PDF do histórico.';
    $entryBreadcrumbs = [
        [$flow->listLabel(), $flow->listUrl()],
        [$coversVehicleName, $flow->vehicleUrl($vehicle)],
        ['Capas'],
    ];
    $entrySteps = $flow->steps(\App\Support\Vehicle\VehicleEntryFlow::STEP_COVERS);
    $coversMaxMb = \App\Support\Vehicle\VehicleEntryFlow::COVER_MAX_KILOBYTES / 1024;
@endphp

@section('entry')
    <x-ui.card as="section" heading-level="h2" title="Fotos de capa" description="Escolha uma foto de cada jeito e ajuste o enquadramento. Só o que fica dentro da moldura aparece." id="capas">
        <form method="POST" action="{{ $flow->url('covers.update', $vehicle) }}" enctype="multipart/form-data" class="space-y-6" data-vehicle-entry-form="covers">
            @csrf
            @method('PUT')

            <x-ui.form-errors id="capas-erros" :ids="['cover' => 'cover', 'cover_portrait' => 'cover_portrait']" />

            <div class="grid gap-6 md:grid-cols-2">
                <x-ui.image-cropper name="cover" aspect="16:9" label="Capa paisagem (celular deitado)"
                    :current="$vehicle->cover_photo_url" current-label="Capa atual" :max-mb="$coversMaxMb" optional />
                <x-ui.image-cropper name="cover_portrait" aspect="9:16" label="Capa retrato (celular em pé)"
                    :current="$vehicle->cover_photo_portrait_url" current-label="Capa atual"
                    hint="Usada em telas estreitas, avatares e no PDF." :max-mb="$coversMaxMb" optional />
            </div>

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
                <x-ui.button variant="secondary" :href="$flow->vehicleUrl($vehicle)" data-skip-covers>Pular por agora</x-ui.button>
                <x-ui.button type="submit" icon="check" loading-label="Enviando capas…">Salvar capas</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection

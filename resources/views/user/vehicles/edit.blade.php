{{--
    Editar veículo (proprietário), em seções:

    1. Fotos de capa: paisagem 16:9 e retrato 9:16, enquadradas no navegador com
       <x-ui.image-cropper> antes do envio (.ai/rules/user-vehicles.md). A capa atual aparece inteira.
    2. Dados do veículo: os campos de user.vehicles._form (documento, modelo e quilometragem).
       As duas seções vão no mesmo envio (User\VehicleController::update), com a barra de ações no
       rodapé (fixa no celular).
    3. Atualizar pelo CRLV-e: formulário à parte, que substitui na hora os dados pelos do documento
       (mesmo RENAVAM). Fica por último e avisa o que muda.
--}}
@extends('layouts.app')

@section('title', 'Editar veículo')

@php
    $editVehicleName = trim($vehicle->brand.' '.$vehicle->model);
    $editDescription = collect([$editVehicleName, filled($vehicle->license_plate) ? 'Placa '.$vehicle->license_plate : null])
        ->filter()
        ->implode(' · ');
    $editCoverMaxMb = 5;
@endphp

@section('content')
    <x-ui.container size="md" padded data-owner-page="vehicle-edit">
        <x-ui.page-header
            title="Editar veículo"
            :description="$editDescription"
            :breadcrumbs="[['Meus veículos', route('user.vehicles.index')], [$editVehicleName, route('user.vehicles.show', $vehicle)], ['Editar']]"
        />

        <div class="space-y-6">
            <form method="POST" action="{{ route('user.vehicles.update', $vehicle) }}" enctype="multipart/form-data" class="space-y-6" data-vehicle-edit-form>
                @csrf
                @method('PUT')

                <x-ui.form-errors id="editar-veiculo-erros" :ids="['cover' => 'cover', 'cover_portrait' => 'cover_portrait']" />

                <x-ui.card
                    as="section"
                    id="capas"
                    heading-level="h2"
                    title="Fotos de capa"
                    description="Escolha uma foto de cada jeito e ajuste o enquadramento. Só o que fica dentro da moldura aparece. Sem foto nova, a capa atual continua."
                >
                    <div class="grid gap-6 md:grid-cols-2">
                        <x-ui.image-cropper
                            name="cover"
                            aspect="16:9"
                            label="Capa paisagem (celular deitado)"
                            :current="$vehicle->cover_photo_url"
                            current-label="Capa atual"
                            :current-alt="'Capa paisagem atual do '.$editVehicleName"
                            hint="Usada em telas largas e no topo da ficha."
                            :max-mb="$editCoverMaxMb"
                            optional
                        />
                        <x-ui.image-cropper
                            name="cover_portrait"
                            aspect="9:16"
                            label="Capa retrato (celular em pé)"
                            :current="$vehicle->cover_photo_portrait_url"
                            current-label="Capa atual"
                            :current-alt="'Capa retrato atual do '.$editVehicleName"
                            hint="Usada em telas estreitas, avatares e no PDF."
                            :max-mb="$editCoverMaxMb"
                            optional
                        />
                    </div>
                </x-ui.card>

                <x-ui.card
                    as="section"
                    id="dados"
                    heading-level="h2"
                    title="Dados do veículo"
                    description="Como estão no documento. A placa nova entra no histórico de placas; o chassi continua identificando o veículo."
                >
                    @include('user.vehicles._form', ['vehicle' => $vehicle, 'catalog' => $catalog])
                </x-ui.card>

                <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
                    <x-ui.button variant="secondary" :href="route('user.vehicles.show', $vehicle)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar alterações</x-ui.button>
                </div>
            </form>

            @include('partials.crlv-import', [
                'importRoute' => route('user.vehicles.import-crlv.edit', $vehicle),
                'inputId' => 'edit_crlv',
                'title' => 'Atualizar pelo CRLV-e',
                'description' => 'Envie o CRLV-e deste veículo (mesmo RENAVAM). Placa, número do CRV, chassi, marca, modelo, ano, cor e motor são substituídos na hora pelos dados do documento, sem passar pelo formulário acima.',
                'submitLabel' => 'Ler CRLV-e e atualizar',
                'loadingLabel' => 'Lendo CRLV-e…',
            ])
        </div>
    </x-ui.container>
@endsection

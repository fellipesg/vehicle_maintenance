{{--
    "Editar veículo e capas" do Lojista (só o dono atual): as duas capas, paisagem 16:9 e retrato 9:16,
    recortadas no navegador antes do envio (.ai/rules/user-vehicles.md e usergaragepublic.md), e os
    dados do veículo (os mesmos campos do assistente, user.vehicles._form). Salvar e Cancelar voltam
    à ficha.
--}}
@extends('layouts.app')

@php
    $vehicleName = trim($vehicle->brand.' '.$vehicle->model);
@endphp

@section('title', 'Editar veículo')

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Editar veículo"
            :description="collect([$vehicleName, $vehicle->license_plate])->filter()->implode(' · ')"
            :breadcrumbs="[['Estoque', route('garage.vehicles.index')], [$vehicleName, route('garage.vehicles.show', $vehicle)], ['Editar']]"
        />

        <form method="POST" action="{{ route('garage.vehicles.update', $vehicle) }}" enctype="multipart/form-data" class="space-y-6" data-garage-vehicle-form>
            @csrf
            @method('PUT')

            <x-ui.form-errors :ids="['cover' => 'cover', 'cover_portrait' => 'cover_portrait']" />

            <x-ui.card as="section" id="capas" heading-level="h2" title="Capas" description="Escolha uma foto de cada jeito e ajuste o enquadramento. Só o que fica dentro da moldura aparece no estoque e na ficha.">
                <div class="grid gap-6 md:grid-cols-2">
                    <x-ui.image-cropper name="cover" aspect="16:9" label="Capa paisagem (celular deitado)"
                        :current="$vehicle->cover_photo_url" current-label="Capa atual" :max-mb="$coverMaxMb" optional />
                    <x-ui.image-cropper name="cover_portrait" aspect="9:16" label="Capa retrato (celular em pé)"
                        :current="$vehicle->cover_photo_portrait_url" current-label="Capa atual"
                        hint="Usada em telas estreitas, avatares e no PDF." :max-mb="$coverMaxMb" optional />
                </div>
            </x-ui.card>

            <x-ui.card as="section" id="dados" heading-level="h2" title="Dados do veículo" description="Os mesmos do CRLV-e. A quilometragem atual não pode ficar abaixo da última manutenção registrada.">
                @include('user.vehicles._form', ['vehicle' => $vehicle, 'catalog' => $catalog])
            </x-ui.card>

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
                <x-ui.button variant="secondary" :href="route('garage.vehicles.show', $vehicle)">Cancelar</x-ui.button>
                <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar alterações</x-ui.button>
            </div>
        </form>
    </x-ui.container>
@endsection

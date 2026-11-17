{{--
    Nova vistoria cautelar, em duas etapas na mesma página como a Nova OS: a etapa 1 confirma o
    veículo (pela placa ou pelo chassi, com o mascaramento de quem não tem vínculo com o carro) e só
    então o laudo aparece.

    Três estados antes do formulário: sem oficina cadastrada (onboarding), oficina sem a
    funcionalidade de laudo ($featureOff, que a tela explica em vez de devolver 403) e tudo em ordem.
--}}
@extends('layouts.app')

@section('title', 'Nova vistoria')

@php
    $formErrorTargets = [
        'license_plate' => 'license_plate',
        'chassis' => 'chassis',
        'new_vehicle*' => 'new_vehicle_brand',
        'report' => 'report',
        'findings.*' => null,
    ];
    $showsForm = $workshop && ! $featureOff && ($vehicle || $creatingVehicle);
@endphp

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Nova vistoria cautelar"
            description="Confirme o veículo e registre o que a vistoria encontrou. O laudo recebe um código de conferência que o comprador confere."
            :breadcrumbs="[['Vistorias', route('workshop.inspections.index')], ['Nova vistoria']]"
        />

        @if(! $workshop)
            <x-ui.empty-state icon="building-storefront" title="Cadastre sua oficina antes de emitir laudos"
                              description="O nome da oficina fica no laudo: é ela que assina o que a vistoria encontrou.">
                <x-slot:actions>
                    <x-ui.button :href="route('workshop.profile.create')">Cadastrar oficina</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @elseif($featureOff)
            <x-ui.empty-state icon="document-text" title="A vistoria cautelar não está ativa nesta conta"
                              description="Emitir laudo é liberado conta a conta pela equipe do RevisaLog, porque o laudo afirma a condição de um carro de terceiro. Fale com a gente para ativar na sua oficina."
                              data-inspections-feature-off>
                <x-slot:actions>
                    <x-ui.button :href="route('contact.show')">Falar com a equipe</x-ui.button>
                    <x-ui.button variant="ghost" :href="route('workshop.dashboard')">Voltar ao Início</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <div class="space-y-6">
                <x-ui.form-errors :ids="$formErrorTargets" />

                @include('workshop._vehicle-step', ['context' => 'inspection', 'readonly' => false])

                @if($showsForm)
                    <form method="POST" action="{{ route('workshop.inspections.store') }}" enctype="multipart/form-data" class="space-y-6" data-inspection-form>
                        @csrf
                        @if($vehicle && filled($vehicle->license_plate) && $chassis === '')
                            {{-- Sem acesso ao veículo inteiro vai a placa digitada: VehiclePlateSearch::findByPlate
                                 resolve o veículo pela placa antiga igual, e a atual não entra no HTML. --}}
                            <input type="hidden" name="license_plate" value="{{ $showsFullVehicle ? $vehicle->license_plate : $licensePlate }}">
                        @else
                            <input type="hidden" name="chassis" value="{{ $chassis !== '' ? $chassis : $vehicle->chassis }}">
                        @endif

                        @if($creatingVehicle)
                            @include('workshop.maintenances._new-vehicle-fields')
                        @endif

                        @if(session()->hasOldInput() && $errors->any())
                            <x-ui.alert variant="warning" role="status" title="Escolha o arquivo de novo">
                                O navegador não guarda arquivos depois de um erro: anexe de novo o PDF do laudo antes de enviar.
                            </x-ui.alert>
                        @endif

                        @include('workshop.inspections._form', ['inspection' => null])

                        <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:-mx-6 sm:px-6" data-slot="form-actions">
                            <x-ui.button variant="secondary" :href="route('workshop.inspections.index')">Cancelar</x-ui.button>
                            <x-ui.button type="submit" icon="check" loading-label="Emitindo o laudo…">Emitir laudo</x-ui.button>
                        </div>
                    </form>
                @endif
            </div>
        @endif
    </x-ui.container>
@endsection

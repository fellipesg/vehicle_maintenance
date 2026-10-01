{{--
    Moldura das telas do assistente de entrada de veículo ("Adicionar veículo" no Proprietário,
    "Adicionar ao estoque" no Lojista; App\Support\Vehicle\VehicleEntryFlow): container de
    formulário, cabeçalho com o único H1 (igual ao <title>), trilha e as etapas.

    A view filha define, fora das seções:
    - $flow (VehicleEntryFlow) e $entryStep (document | review | claim | power_of_attorney | covers);
    - $entryTitle (H1 e <title>), $entryEyebrow e $entryDescription (opcionais);
    - $entryBreadcrumbs (itens do x-ui.breadcrumb) e $entrySteps ($flow->steps(...)).
    e escreve o corpo em @section('entry').
--}}
@extends('layouts.app')

@section('title', $entryTitle)

@section('content')
    <x-ui.container size="md" padded data-vehicle-entry="{{ $entryStep }}" data-portal="{{ $flow->portal->value }}">
        <x-ui.page-header
            :title="$entryTitle"
            :eyebrow="$entryEyebrow ?? null"
            :description="$entryDescription ?? null"
            :breadcrumbs="$entryBreadcrumbs"
        />

        <x-ui.stepper
            :label="'Etapas: '.$flow->title()"
            :steps="$entrySteps['items']"
            :current="$entrySteps['current']"
        />

        <div class="space-y-6">
            @yield('entry')
        </div>
    </x-ui.container>
@endsection

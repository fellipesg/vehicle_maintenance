{{--
    Passo 2 do assistente para veículo novo, Conferir: o que o CRLV-e trouxe, o formulário para
    conferir e completar (quilometragem) e o aceite dos termos. No Lojista, avisa antes do envio se
    o veículo vai entrar como consignação (conta sem CNPJ ou CRLV-e de outra pessoa).
--}}
@extends('vehicles.entry.layout')

@php
    $consignmentExpected = $ownership !== \App\Support\Vehicle\VehicleEntryFlow::OWNERSHIP_OWNER;
    $entryStep = \App\Support\Vehicle\VehicleEntryFlow::STEP_REVIEW;
    $entryTitle = 'Conferir dados do veículo';
    $entryEyebrow = 'Novo veículo';
    $entryDescription = 'Este veículo ainda não está na RevisaLog. Confira o que lemos'
        .(filled($sourceFile) ? ' em '.$sourceFile : ' no CRLV-e')
        .' e informe a quilometragem atual.';
    $entryBreadcrumbs = [
        [$flow->listLabel(), $flow->listUrl()],
        [$flow->title(), $flow->url('create')],
        ['Conferir dados'],
    ];
    $entrySteps = $flow->steps(\App\Support\Vehicle\VehicleEntryFlow::STEP_REVIEW, $consignmentExpected);
@endphp

@section('entry')
    @include('vehicles.entry._ownership-notice', ['ownership' => $ownership, 'flow' => $flow])

    <x-ui.card as="section" heading-level="h2" title="Dados lidos do CRLV-e" id="dados-crlv">
        @include('vehicles.entry._crlv-summary', ['preview' => $preview, 'flow' => $flow, 'accountDocument' => $accountDocument])
    </x-ui.card>

    <x-ui.card as="section" heading-level="h2" title="Conferir e completar" description="Corrija o que estiver diferente do documento. Os campos com * são obrigatórios." id="conferir">
        <form method="POST" action="{{ $flow->url('store') }}" class="space-y-6" data-vehicle-entry-form="review">
            @csrf
            <input type="hidden" name="crlv_verification_token" value="{{ old('crlv_verification_token', $preview['crlv_verification_token'] ?? '') }}">

            <x-ui.form-errors
                id="conferir-erros"
                :threshold="$errors->has('vehicle') ? 1 : 2"
                :ids="['vehicle' => null, 'crlv_verification_token' => null, 'terms_accepted' => 'terms-accepted-checkbox']"
            />

            @include('user.vehicles._form', [
                'catalog' => $catalog,
                'vehicle' => (object) $preview,
            ])

            <x-terms-scroll-accept />

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
                <x-ui.button variant="secondary" icon="arrow-left" :href="$flow->url('create')">Voltar</x-ui.button>
                @if($consignmentExpected)
                    <x-ui.button type="submit" icon-trailing="arrow-right" loading-label="Salvando…" data-terms-submit>Continuar para a procuração</x-ui.button>
                @else
                    <x-ui.button type="submit" icon="plus" loading-label="Adicionando…" data-terms-submit>{{ $flow->addLabel() }}</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>
@endsection

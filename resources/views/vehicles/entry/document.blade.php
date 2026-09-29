{{--
    Passo 1 do assistente, Documento: ler o CRLV-e é a ação principal (a leitura decide entre veículo
    novo, vínculo e procuração); o formulário manual fica recolhido e abre sozinho quando volta com
    erro ou com os dados digitados.
--}}
@extends('vehicles.entry.layout')

@php
    $entryStep = \App\Support\Vehicle\VehicleEntryFlow::STEP_DOCUMENT;
    $entryTitle = $flow->title();
    $entryDescription = $flow->documentDescription();
    $entryBreadcrumbs = [[$flow->listLabel(), $flow->listUrl()], [$flow->title()]];
    $entrySteps = $flow->steps(\App\Support\Vehicle\VehicleEntryFlow::STEP_DOCUMENT);

    $manualFields = ['chassis', 'license_plate', 'renavam', 'crv_number', 'brand', 'model', 'year', 'color', 'motorization', 'engine', 'current_kilometers', 'terms_accepted', 'vehicle'];
    $manualOpen = $errors->hasAny($manualFields)
        || collect($manualFields)->contains(fn (string $field): bool => filled(old($field)));
@endphp

@section('entry')
    @if($vehicleExists)
        <x-ui.alert variant="info" title="Este veículo já está na RevisaLog" data-vehicle-exists>
            Não é preciso cadastrá-lo de novo. Envie o CRLV-e dele abaixo: a leitura encontra o cadastro e leva à confirmação do vínculo, com o histórico que já existe.
        </x-ui.alert>
    @endif

    @if($accountDocumentMissing)
        <x-ui.alert variant="warning" role="status" title="Sua conta ainda não tem o CNPJ da loja" data-account-document-missing>
            Sem ele, não conseguimos confirmar que um veículo é da loja, e todo CRLV-e entra como consignação, com procuração. <a href="{{ route('contact.show') }}">Fale com a equipe</a> para incluir o CNPJ.
        </x-ui.alert>
    @endif

    @include('partials.crlv-import', [
        'importRoute' => $flow->url('import-crlv'),
        'title' => 'Ler o CRLV-e',
        'recommended' => true,
        'description' => 'Exporte o PDF no app Carteira Digital de Trânsito (CDT). Lemos placa, RENAVAM, número do CRV e chassi; se o veículo já estiver na RevisaLog, você confirma o vínculo e fica com o histórico.',
    ])

    <details class="group rounded-card border border-border bg-surface shadow-sm" data-vehicle-entry-manual @if($manualOpen) open @endif>
        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 rounded-card px-4 py-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:px-6 [&::-webkit-details-marker]:hidden">
            <span class="min-w-0">
                <span class="block text-base font-semibold text-foreground">Não tenho o CRLV-e agora: preencher manualmente</span>
                <span class="mt-1 block text-sm text-muted-foreground">Digite os dados como estão no documento. Sem o CRLV-e, a propriedade fica sem confirmação.</span>
            </span>
            <x-ui.icon name="chevron-down" class="size-5 shrink-0 text-muted-foreground transition-transform duration-fast ease-smooth-out group-open:rotate-180 motion-reduce:transition-none" />
        </summary>

        <div class="border-t border-border px-4 py-4 sm:px-6 sm:py-6">
            <form method="POST" action="{{ $flow->url('store') }}" class="space-y-6" data-vehicle-entry-form="manual">
                @csrf

                <x-ui.form-errors
                    id="manual-erros"
                    :threshold="$errors->has('vehicle') ? 1 : 2"
                    :ids="['vehicle' => null, 'crlv' => null, 'terms_accepted' => 'terms-accepted-checkbox']"
                />

                @include('user.vehicles._form', ['catalog' => $catalog, 'vehicle' => new \App\Models\Vehicle])

                <x-terms-scroll-accept />

                <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
                    <x-ui.button variant="secondary" :href="$flow->listUrl()">Cancelar</x-ui.button>
                    <x-ui.button type="submit" icon="plus" loading-label="Adicionando…" data-terms-submit>{{ $flow->addLabel() }}</x-ui.button>
                </div>
            </form>
        </div>
    </details>
@endsection

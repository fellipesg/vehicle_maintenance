{{--
    Passo 2 do assistente quando o veículo já está na RevisaLog, Conferir: o veículo encontrado, o
    histórico que vem junto (com Selo da oficina × declaradas), o que o CRLV-e trouxe e a confirmação
    do vínculo. Erro do vínculo (ex.: já vinculado) aparece no topo, sem beco sem saída.
--}}
@extends('vehicles.entry.layout')

@php
    $consignmentExpected = $ownership !== \App\Support\Vehicle\VehicleEntryFlow::OWNERSHIP_OWNER;
    $entryStep = 'claim';
    $entryTitle = $flow->claimTitle();
    $entryEyebrow = 'Veículo já cadastrado';
    $entryDescription = 'Este veículo já está na RevisaLog. Confira o CRLV-e e confirme: o histórico registrado por donos anteriores e por oficinas continua no chassi.';
    $entryBreadcrumbs = [
        [$flow->listLabel(), $flow->listUrl()],
        [$flow->title(), $flow->url('create')],
        ['Vincular veículo'],
    ];
    $entrySteps = $flow->steps(\App\Support\Vehicle\VehicleEntryFlow::STEP_REVIEW, $consignmentExpected && ! $alreadyLinked);

    $claimTotal = (int) ($vehicle->maintenances_count ?? 0);
    $claimSealed = (int) ($vehicle->verified_maintenances_count ?? 0);
    $claimDeclared = max(0, $claimTotal - $claimSealed);
    $claimName = trim($vehicle->brand.' '.$vehicle->model);
@endphp

@section('entry')
    <x-ui.form-errors
        id="vincular-erros"
        :threshold="1"
        title="Não foi possível vincular o veículo"
        :ids="['vehicle' => null, 'crlv' => null, 'crlv_verification_token' => null]"
    />

    @if($alreadyLinked)
        <x-ui.alert variant="info" :title="$flow->isDealer() ? 'Este veículo já está no seu estoque' : 'Este veículo já está na sua conta'" data-already-linked>
            Não é preciso vincular de novo.
            <x-slot:actions>
                <x-ui.button variant="secondary" size="sm" icon-trailing="arrow-right" :href="$flow->vehicleUrl($vehicle)">Abrir o veículo</x-ui.button>
            </x-slot:actions>
        </x-ui.alert>
    @else
        @include('vehicles.entry._ownership-notice', ['ownership' => $ownership, 'flow' => $flow])
    @endif

    <x-ui.card as="section" heading-level="h2" title="Veículo encontrado" id="veiculo-encontrado">
        <div class="flex items-start gap-4">
            <x-vehicle-cover :vehicle="$vehicle" />
            <div class="min-w-0 space-y-2">
                <p class="font-semibold text-foreground">{{ $claimName }}@if($vehicle->year) <span class="font-normal text-muted-foreground">· {{ $vehicle->year }}</span>@endif</p>
                <p class="text-sm text-muted-foreground">Placa atual <span class="font-mono font-semibold tracking-wider text-foreground">{{ $vehicle->license_plate }}</span></p>
                <div data-claim-history>
                    @if($claimTotal > 0)
                        <p class="text-sm text-foreground">{{ \App\Support\Vehicle\VehicleMaintenanceHistory::countLabel($claimTotal) }} no histórico, que passa a aparecer para você:</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if($claimSealed > 0)
                                <x-ui.badge variant="seal">{{ $claimSealed }} com Selo da oficina</x-ui.badge>
                            @endif
                            @if($claimDeclared > 0)
                                <x-ui.badge variant="declared">{{ \App\Support\Vehicle\VehicleMaintenanceHistory::declaredLabel($claimDeclared) }}</x-ui.badge>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-muted-foreground">Nenhuma manutenção registrada ainda.</p>
                    @endif
                </div>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card as="section" heading-level="h2" title="Dados lidos do CRLV-e" :description="filled($sourceFile) ? 'Arquivo: '.$sourceFile : null" id="dados-crlv">
        @include('vehicles.entry._crlv-summary', ['preview' => $preview, 'flow' => $flow, 'accountDocument' => $accountDocument, 'catalogHint' => false])
    </x-ui.card>

    <form method="POST" action="{{ $flow->url('claim.store') }}" data-vehicle-entry-form="claim">
        @csrf
        <input type="hidden" name="crlv_verification_token" value="{{ $preview['crlv_verification_token'] ?? '' }}">

        <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
            <x-ui.button variant="secondary" icon="arrow-left" :href="$flow->url('create')">Voltar</x-ui.button>
            @unless($alreadyLinked)
                @if($consignmentExpected)
                    <x-ui.button type="submit" icon-trailing="arrow-right" loading-label="Salvando…">Continuar para a procuração</x-ui.button>
                @else
                    <x-ui.button type="submit" icon="link" loading-label="Vinculando…">{{ $flow->claimLabel() }}</x-ui.button>
                @endif
            @endunless
        </div>
    </form>
@endsection

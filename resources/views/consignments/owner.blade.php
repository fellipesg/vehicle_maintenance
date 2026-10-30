{{--
    Página que o proprietário de um veículo em consignação abre pelo link do e-mail. Sem login de
    propósito: o dono de um carro consignado quase nunca tem conta, e exigir cadastro para ele poder
    dizer "não autorizei essa loja" derrubaria o propósito. O token da URL é o segredo.

    Ela lista só o que a loja registrou — o histórico anterior é dele e não precisa ser repetido aqui.
--}}
@extends('layouts.app')

@section('title', 'Seu veículo em consignação')

@php
    $vehicle = $consignment->vehicle;
    $vehicleName = trim($vehicle->brand.' '.$vehicle->model);

    // Quem o proprietário conhece é a loja, não a pessoa dona da conta.
    $storeIdentity = $consignment->garageUser->storeIdentity();
@endphp

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Seu veículo está em consignação"
            :description="'A loja '.$storeIdentity['name'].' informou que está com o seu '.$vehicleName.(filled($vehicle->license_plate) ? ' ('.$vehicle->license_plate.')' : '').' para venda desde '.$consignment->started_at->format('d/m/Y').'.'"
        />

        @if($consignment->isDisputed())
            <x-ui.alert variant="danger" title="Você contestou esta consignação" class="mb-6" data-consignment-state="disputed">
                A loja não pode mais registrar manutenções neste veículo. A equipe RevisaLog vai analisar o caso.
            </x-ui.alert>
        @elseif(! $consignment->isActive())
            <x-ui.alert variant="info" title="Consignação encerrada" class="mb-6" data-consignment-state="ended">
                Esta consignação foi encerrada em {{ $consignment->ended_at?->format('d/m/Y') }}. As manutenções registradas continuam no histórico do veículo.
            </x-ui.alert>
        @else
            <x-ui.alert variant="info" title="O que isso significa" class="mb-6" data-consignment-state="active">
                A loja pode registrar as manutenções que fizer no veículo, e você recebe um aviso a cada registro. Esses
                registros ficam no histórico do carro, o que costuma valorizá-lo na venda.
            </x-ui.alert>
        @endif

        <x-ui.card as="section" heading-level="h2" title="A loja" class="mb-6" data-slot="store-contact">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div class="min-w-0 sm:col-span-2">
                    <dt class="text-muted-foreground">Nome</dt>
                    <dd class="font-semibold text-foreground">{{ $storeIdentity['name'] }}</dd>
                </div>
                @if($storeIdentity['address'] !== null)
                    <div class="min-w-0 sm:col-span-2">
                        <dt class="text-muted-foreground">Endereço</dt>
                        <dd class="text-foreground">{{ $storeIdentity['address'] }}</dd>
                    </div>
                @endif
                @if(filled($storeIdentity['phone']))
                    <div class="min-w-0">
                        <dt class="text-muted-foreground">Telefone</dt>
                        <dd><x-ui.link :href="'tel:'.preg_replace('/\D+/', '', (string) $storeIdentity['phone'])" variant="inline">{{ $storeIdentity['phone'] }}</x-ui.link></dd>
                    </div>
                @endif
                @if(filled($storeIdentity['email']))
                    <div class="min-w-0">
                        <dt class="text-muted-foreground">E-mail</dt>
                        <dd class="truncate"><x-ui.link :href="'mailto:'.$storeIdentity['email']" variant="inline">{{ $storeIdentity['email'] }}</x-ui.link></dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card as="section" heading-level="h2" title="Manutenções registradas pela loja" class="mb-6">
            @forelse($maintenances as $maintenance)
                <div class="border-t border-border py-3 text-sm first:border-t-0 first:pt-0">
                    <p class="font-medium text-foreground">{{ $maintenance->maintenance_type }}</p>
                    <p class="text-muted-foreground">
                        {{ $maintenance->maintenance_date->format('d/m/Y') }} ·
                        {{ number_format((int) $maintenance->kilometers, 0, ',', '.') }} km
                    </p>
                </div>
            @empty
                <p class="text-sm text-muted-foreground">Nenhuma manutenção registrada até agora.</p>
            @endforelse
        </x-ui.card>

        @if($consignment->isActive() && ! $consignment->isDisputed())
            <x-ui.card as="section" heading-level="h2" title="Histórico anterior do veículo" class="mb-6">
                @if($consignment->grantsHistoryAccess())
                    <p class="text-sm text-muted-foreground" data-history-state="approved">
                        Você liberou o histórico para esta loja. Ela consegue ver as manutenções registradas antes da consignação.
                    </p>
                @else
                    <p class="text-sm text-muted-foreground" data-history-state="restricted">
                        Hoje a loja <strong class="font-semibold text-foreground">não vê</strong> as manutenções registradas antes
                        da consignação: esse histórico é seu. Liberar costuma ajudar na venda, porque o comprador enxerga o
                        cuidado que o carro teve.
                    </p>
                    @if($consignment->power_of_attorney_path)
                        <p class="mt-2 text-sm text-muted-foreground">
                            A loja anexou uma procuração pedindo esse acesso. Você pode liberar agora, sem esperar a nossa análise.
                        </p>
                    @endif
                    <form method="POST" action="{{ route('consignments.owner.approve', $consignment->owner_action_token) }}" class="mt-4">
                        @csrf
                        <x-ui.button type="submit" icon="shield-check" loading-label="Liberando…">Liberar histórico para a loja</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card as="section" heading-level="h2" title="Não autorizei esta loja">
                <p class="text-sm text-muted-foreground">
                    Ao contestar, a loja deixa imediatamente de registrar manutenções neste veículo e a equipe RevisaLog analisa o caso.
                </p>
                <form method="POST" action="{{ route('consignments.owner.dispute', $consignment->owner_action_token) }}" class="mt-4 space-y-4">
                    @csrf
                    <x-ui.field name="note" label="Quer nos contar o que aconteceu?" optional>
                        <x-ui.textarea rows="3" maxlength="1000" />
                    </x-ui.field>
                    <x-ui.button variant="secondary" type="submit" loading-label="Registrando…">Contestar consignação</x-ui.button>
                </form>
            </x-ui.card>
        @endif

        @error('consignment')
            <x-ui.alert variant="danger" class="mt-6">{{ $message }}</x-ui.alert>
        @enderror
    </x-ui.container>
@endsection

@extends('layouts.app')

@section('title', 'Seu veículo em consignação — Revisalog')

@section('content')
@php($vehicle = $consignment->vehicle)
<div class="mx-auto max-w-2xl px-4 py-10">
    <p class="text-sm font-semibold uppercase tracking-wide text-automotive-500">Revisalog</p>
    <h1 class="mt-2 text-2xl font-bold text-automotive-900">Seu veículo está em consignação</h1>

    <div class="card mt-6">
        <p class="text-sm text-automotive-700">
            A garagem <strong>{{ $consignment->garageUser->name }}</strong> informou que está com o seu
            <strong>{{ $vehicle->brand }} {{ $vehicle->model }}</strong> ({{ $vehicle->license_plate }}) para venda,
            desde {{ $consignment->started_at->format('d/m/Y') }}.
        </p>
        <p class="mt-2 text-sm text-automotive-600">
            Ela pode registrar as manutenções que fizer no veículo. Você recebe um aviso a cada registro, e esses
            registros ficam no histórico do carro — o que costuma valorizá-lo na venda.
        </p>
    </div>

    @if($consignment->isDisputed())
        <div class="card mt-6 border-l-4 border-red-400">
            <p class="font-semibold text-red-700">Você contestou esta consignação.</p>
            <p class="mt-1 text-sm text-automotive-600">
                A garagem não pode mais registrar manutenções neste veículo. Nossa equipe vai analisar o caso.
            </p>
        </div>
    @elseif(! $consignment->isActive())
        <div class="card mt-6">
            <p class="text-sm text-automotive-700">
                Esta consignação foi encerrada em {{ $consignment->ended_at?->format('d/m/Y') }}.
            </p>
        </div>
    @endif

    <div class="card mt-6">
        <h2 class="text-lg font-semibold text-automotive-900">Manutenções registradas pela garagem</h2>
        @forelse($maintenances as $maintenance)
            <div class="mt-3 border-t border-automotive-100 pt-3 text-sm">
                <p class="font-medium text-automotive-800">{{ $maintenance->maintenance_type }}</p>
                <p class="text-automotive-600">
                    {{ $maintenance->maintenance_date->format('d/m/Y') }} ·
                    {{ number_format((int) $maintenance->kilometers, 0, ',', '.') }} km
                </p>
            </div>
        @empty
            <p class="mt-2 text-sm text-automotive-500">Nenhuma manutenção registrada até agora.</p>
        @endforelse
    </div>

    @if($consignment->isActive() && ! $consignment->isDisputed())
        <div class="card mt-6">
            <h2 class="text-lg font-semibold text-automotive-900">Histórico anterior do veículo</h2>

            @if($consignment->grantsHistoryAccess())
                <p class="mt-2 text-sm text-automotive-700">
                    Você liberou o histórico para esta garagem. Ela consegue ver as manutenções registradas antes da consignação.
                </p>
            @else
                <p class="mt-2 text-sm text-automotive-700">
                    Hoje a garagem <strong>não vê</strong> as manutenções registradas antes da consignação — esse histórico é seu.
                    Liberar costuma ajudar na venda, porque o comprador enxerga o cuidado que o carro teve.
                </p>
                @if($consignment->power_of_attorney_path)
                    <p class="mt-2 text-sm text-automotive-500">
                        A garagem anexou uma procuração pedindo esse acesso. Você pode liberar agora, sem esperar a nossa análise.
                    </p>
                @endif
                <form method="POST" action="{{ route('consignments.owner.approve', $consignment->owner_action_token) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="btn-primary">Liberar histórico para a garagem</button>
                </form>
            @endif
        </div>

        <details class="card mt-6">
            <summary class="cursor-pointer font-semibold text-automotive-900">Não autorizei esta garagem</summary>
            <p class="mt-3 text-sm text-automotive-600">
                Ao contestar, a garagem deixa imediatamente de registrar manutenções neste veículo e nossa equipe analisa o caso.
            </p>
            <form method="POST" action="{{ route('consignments.owner.dispute', $consignment->owner_action_token) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="note" class="form-label">Quer nos contar o que aconteceu? (opcional)</label>
                    <textarea name="note" id="note" rows="3" class="form-input" maxlength="1000"></textarea>
                    @error('note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-secondary">Contestar consignação</button>
            </form>
        </details>
    @endif

    @error('consignment')<p class="mt-4 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
@endsection

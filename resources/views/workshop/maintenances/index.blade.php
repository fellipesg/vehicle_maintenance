@extends('layouts.app')

@section('title', 'Ordens de serviço')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="badge badge-orange mb-2">🔧 Oficina</span>
            <h1 class="text-3xl font-bold">Ordens de serviço</h1>
        </div>
        @if($workshop)
            <a href="{{ route('workshop.maintenances.create') }}" class="btn-primary">Nova OS</a>
        @endif
    </div>

    @if(!$workshop)
        <div class="card text-center">
            <p class="text-automotive-500">Cadastre sua oficina para ver os serviços vinculados.</p>
            <a href="{{ route('workshop.profile.create') }}" class="btn-primary mt-4">Cadastrar oficina</a>
        </div>
    @else
        <x-provenance-legend class="mb-4" />

        @forelse($maintenances as $maintenance)
            <x-provenance-card
                :maintenance="$maintenance"
                :href="route('workshop.maintenances.show', $maintenance)"
                class="mb-3"
            />
            <p class="-mt-2 mb-3 ml-[calc(28px+0.75rem+3px+1rem)] text-sm text-automotive-600">
                {{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }}
                · {{ $maintenance->vehicle->license_plate }}
                · {{ $maintenance->maintenance_date->format('d/m/Y') }}
                @if($maintenance->user)
                    · Registrado por: {{ $maintenance->user->name }}
                @endif
            </p>
        @empty
            <div class="card text-center text-automotive-500">
                <p>Nenhum serviço vinculado à sua oficina.</p>
                <a href="{{ route('workshop.maintenances.create') }}" class="btn-primary mt-4 inline-block">Registrar primeira OS</a>
            </div>
        @endforelse

        <div class="mt-4">{{ $maintenances->links() }}</div>
    @endif
</div>
@endsection

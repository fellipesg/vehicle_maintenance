@extends('layouts.app')

@section('title', 'Serviços')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <span class="badge badge-orange mb-2">🔧 Oficina</span>
    <h1 class="mb-6 text-3xl font-bold">Serviços Realizados</h1>

    @if(!$workshop)
        <div class="card text-center">
            <p class="text-automotive-500">Cadastre sua oficina para ver os serviços vinculados.</p>
            <a href="{{ route('workshop.profile.create') }}" class="btn-primary mt-4">Cadastrar oficina</a>
        </div>
    @else
        @forelse($maintenances as $maintenance)
            <div class="card mb-3">
                <p class="font-semibold">{{ $maintenance->maintenance_type }}</p>
                <p class="text-sm text-automotive-600">
                    {{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->vehicle->license_plate }}
                    · {{ $maintenance->maintenance_date->format('d/m/Y') }}
                </p>
                @if($maintenance->user)<p class="text-xs text-automotive-500">Registrado por: {{ $maintenance->user->name }}</p>@endif
            </div>
        @empty
            <div class="card text-center text-automotive-500">Nenhum serviço vinculado à sua oficina.</div>
        @endforelse

        <div class="mt-4">{{ $maintenances->links() }}</div>
    @endif
</div>
@endsection

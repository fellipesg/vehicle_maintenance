@extends('layouts.app')

@section('title', 'Manutenções')

@php
    $categories = [
        'mechanical' => 'Mecânica', 'electrical' => 'Elétrica', 'suspension' => 'Suspensão',
        'painting' => 'Pintura', 'finishing' => 'Acabamento', 'interior' => 'Interior', 'other' => 'Outros',
    ];
@endphp

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold">🔧 Manutenções</h1>
        <a href="{{ route('user.maintenances.create') }}" class="btn-primary">+ Nova Manutenção</a>
    </div>

    @forelse($maintenances as $maintenance)
        <a href="{{ route('user.maintenances.show', $maintenance) }}" class="card mb-3 block hover:border-wrench-300">
            <div class="flex justify-between">
                <div>
                    <span class="badge badge-orange">{{ $categories[$maintenance->service_category] ?? '' }}</span>
                    <p class="mt-1 font-semibold">{{ $maintenance->maintenance_type }}</p>
                    <p class="text-sm text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->vehicle->license_plate }}</p>
                </div>
                <div class="text-right text-sm text-automotive-500">
                    <p>{{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
                    @if($maintenance->workshop)<p>🔧 {{ $maintenance->workshop->name }}</p>@endif
                </div>
            </div>
        </a>
    @empty
        <div class="card text-center text-automotive-500">
            <p>Nenhuma manutenção registrada.</p>
            <a href="{{ route('user.maintenances.create') }}" class="btn-primary mt-4">Registrar manutenção</a>
        </div>
    @endforelse

    <div class="mt-4">{{ $maintenances->links() }}</div>
</div>
@endsection

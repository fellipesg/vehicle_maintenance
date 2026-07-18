@extends('layouts.app')

@section('title', $user->name)

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.dashboard') }}" class="text-sm text-wrench-600 hover:underline">← Voltar ao painel</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">{{ $user->name }}</h1>
                <p class="text-automotive-600">{{ $user->email }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="badge {{ $user->typeBadgeClass() }}">{{ $user->typeLabel() }}</span>
                    @if($user->is_admin)
                        <span class="badge badge-orange">Administrador</span>
                    @endif
                    @if($user->phone)
                        <span class="text-sm text-automotive-500">📞 {{ $user->phone }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Veículos atuais</p>
            <p class="text-3xl font-bold text-automotive-900">{{ $user->vehicles->count() }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Manutenções registradas</p>
            <p class="text-3xl font-bold text-automotive-900">{{ $user->vehicles->sum(fn ($v) => $v->maintenances->count()) }}</p>
        </div>
    </div>

    <h2 class="mb-4 text-xl font-semibold">🚗 Veículos e manutenções</h2>

    @forelse($user->vehicles as $vehicle)
        <div class="card mb-4">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">{{ $vehicle->brand }} {{ $vehicle->model }}</h3>
                    <p class="text-sm text-automotive-600">
                        {{ $vehicle->year }} · {{ $vehicle->license_plate }} · {{ $vehicle->color ?? '—' }}
                    </p>
                    @if($vehicle->motorization)
                        <p class="text-sm text-automotive-500">Motorização: {{ $vehicle->motorization }}</p>
                    @endif
                </div>
                <span class="badge badge-blue">{{ $vehicle->maintenances->count() }} manutenções</span>
            </div>

            @if($vehicle->maintenances->isNotEmpty())
                <div class="space-y-2 border-t border-automotive-100 pt-4">
                    @foreach($vehicle->maintenances as $maintenance)
                        <div class="rounded-lg border border-automotive-100 px-4 py-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium">{{ $maintenance->maintenance_type }}</p>
                                    <p class="text-sm text-automotive-600">
                                        {{ $maintenance->maintenance_date->format('d/m/Y') }}
                                        @if($maintenance->kilometers)
                                            · {{ number_format($maintenance->kilometers, 0, ',', '.') }} km
                                        @endif
                                    </p>
                                    @if($maintenance->workshop_name)
                                        <p class="text-sm text-automotive-500">🔧 {{ $maintenance->workshop_name }}</p>
                                    @endif
                                </div>
                                @if($maintenance->service_category)
                                    <span class="badge badge-green">{{ $maintenance->service_category }}</span>
                                @endif
                            </div>
                            @if($maintenance->description)
                                <p class="mt-2 text-sm text-automotive-600 whitespace-pre-line">{{ Str::limit($maintenance->description, 200) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="border-t border-automotive-100 pt-4 text-sm text-automotive-500">Nenhuma manutenção registrada para este veículo.</p>
            @endif
        </div>
    @empty
        <div class="card text-center text-automotive-500">
            Este usuário ainda não possui veículos cadastrados.
        </div>
    @endforelse
</div>
@endsection

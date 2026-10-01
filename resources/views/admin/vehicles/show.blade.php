@extends('layouts.admin')

@section('title', trim($vehicle->brand.' '.$vehicle->model))

@php
    $vehicleName = trim($vehicle->brand.' '.$vehicle->model);
    $adminBreadcrumbs = [['Frota'], ['Veículos', route('admin.vehicles.index')], [$vehicleName]];
    $owner = $vehicle->owners->first();
@endphp

@section('content')
    {{--
        A mesma ficha dos portais (<x-vehicle.detail>), só leitura: o admin vê os documentos completos
        e o histórico com a procedência; a trilha fica na topbar do admin.
    --}}
    <x-vehicle.detail :vehicle="$vehicle" :portal="\App\Enums\Portal::Admin">
        <x-slot:actions>
            @if($vehicle->maintenances()->exists())
                <x-ui.button variant="secondary" icon="wrench-screwdriver" :href="route('admin.maintenances.index', ['veiculo' => $vehicle->id])">Ver na lista de manutenções</x-ui.button>
            @endif
            @if($owner)
                <x-ui.button variant="secondary" icon="user-circle" :href="route('admin.users.show', $owner)">Abrir proprietário atual</x-ui.button>
            @endif
        </x-slot:actions>

        <x-slot:notice>
            <dl class="flex flex-wrap gap-x-6 gap-y-2 rounded-card border border-border bg-surface px-4 py-3 text-sm" data-slot="admin-vehicle-ownership">
                <div class="flex flex-wrap items-center gap-1.5">
                    <dt class="text-muted-foreground">Proprietário atual</dt>
                    <dd class="flex flex-wrap items-center gap-1.5">
                        @if($owner)
                            <x-ui.link :href="route('admin.users.show', $owner)">{{ $owner->name }}</x-ui.link>
                            <x-admin.user-type-badge :user="$owner" size="sm" />
                        @else
                            <span class="text-foreground">Sem proprietário atual</span>
                        @endif
                    </dd>
                </div>
                @if($vehicle->created_at)
                    <div class="flex gap-1.5">
                        <dt class="text-muted-foreground">Cadastro na plataforma</dt>
                        <dd class="text-foreground tabular-nums">{{ $vehicle->created_at->format('d/m/Y') }}</dd>
                    </div>
                @endif
            </dl>
        </x-slot:notice>
    </x-vehicle.detail>
@endsection

@php
    $maintenanceCount = $vehicle->maintenances->count();
@endphp

@if(! $canViewMaintenances && $maintenanceCount > 0)
    <div class="relative overflow-hidden rounded-xl border border-automotive-200">
        <div class="space-y-3 p-4 blur-sm select-none" aria-hidden="true">
            @foreach($vehicle->maintenances->take(3) as $maintenance)
                <div class="rounded-lg bg-automotive-50 p-4">
                    <p class="font-semibold">{{ $maintenance->maintenance_type }}</p>
                    <p class="text-sm text-automotive-500">{{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
                </div>
            @endforeach
        </div>
        <div class="absolute inset-0 flex flex-col items-center justify-center bg-white/75 px-6 text-center">
            <p class="text-lg font-semibold text-automotive-900">
                {{ $maintenanceCount }} manutenção(ões) registrada(s)
            </p>
            <p class="mt-2 max-w-md text-sm text-automotive-600">
                Assine para visualizar o histórico completo deste veículo, incluindo itens e notas fiscais.
            </p>
            <button type="button" class="btn-primary mt-4" disabled title="Em breve">
                Assinar e liberar histórico
            </button>
            <p class="mt-2 text-xs text-automotive-500">Planos de assinatura em breve</p>
        </div>
    </div>
@else
    @forelse($vehicle->maintenances->sortByDesc('maintenance_date') as $maintenance)
        <a href="{{ route($maintenanceShowRoute ?? 'user.maintenances.show', $maintenance) }}" class="card mb-3 block hover:border-wrench-300">
            <div class="flex justify-between">
                <div>
                    <span class="badge badge-orange">{{ $categories[$maintenance->service_category] ?? '' }}</span>
                    <p class="mt-1 font-semibold">{{ $maintenance->maintenance_type }}</p>
                    <p class="text-sm text-automotive-600">{{ Str::limit($maintenance->description, 100) }}</p>
                </div>
                <div class="text-right text-sm text-automotive-500">
                    <p>{{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
                    @if($maintenance->kilometers)<p>{{ number_format($maintenance->kilometers, 0, ',', '.') }} km</p>@endif
                </div>
            </div>
        </a>
    @empty
        <div class="card text-center text-automotive-500">Nenhuma manutenção registrada.</div>
    @endforelse
@endif

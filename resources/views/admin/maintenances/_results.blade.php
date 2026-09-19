<div class="card overflow-hidden !p-0">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-automotive-200 bg-automotive-50">
                <tr>
                    <th class="px-4 py-3 font-semibold">Data</th>
                    <th class="px-4 py-3 font-semibold">Veículo</th>
                    <th class="px-4 py-3 font-semibold">Procedência</th>
                    <th class="px-4 py-3 font-semibold">Oficina</th>
                    <th class="px-4 py-3 font-semibold">Tipo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($maintenances as $maintenance)
                    @php
                        $provClass = $maintenance->isVerified() ? 'prov-verified' : 'prov-declared';
                    @endphp
                    <tr class="border-b border-automotive-100 {{ $provClass }}">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $maintenance->maintenance_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            @if($maintenance->vehicle)
                                <a href="{{ route('admin.vehicles.show', $maintenance->vehicle) }}" class="font-medium text-wrench-600 hover:underline">
                                    {{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }}
                                </a>
                                <span class="text-automotive-500">· {{ $maintenance->vehicle->license_plate }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-start gap-2 {{ $provClass }}">
                                <x-provenance-marker :maintenance="$maintenance" size="sm" />
                                <div class="min-w-0">
                                    <p class="font-medium text-automotive-900">{{ $maintenance->provenance_card_label }}</p>
                                    <p class="text-xs text-automotive-500">{{ $maintenance->provenance_meta }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $maintenance->workshop?->name ?? $maintenance->workshop_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-automotive-600">{{ $maintenance->maintenance_type }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-automotive-600">Nenhuma manutenção.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4" data-admin-maintenances-pagination>
    {{ $maintenances->links() }}
</div>

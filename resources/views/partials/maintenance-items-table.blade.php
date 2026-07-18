@php
    $items = $items ?? collect();
@endphp

@if($items->isEmpty())
    <p class="text-sm text-automotive-500">Nenhum item registrado.</p>
@else
    <div class="overflow-x-auto rounded-lg border border-automotive-200">
        <table class="min-w-full divide-y divide-automotive-200 text-sm">
            <thead class="bg-automotive-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-semibold text-automotive-700">Item</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold text-automotive-700 whitespace-nowrap">Qtd.</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold text-automotive-700 whitespace-nowrap">Preço unit.</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold text-automotive-700 whitespace-nowrap">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-automotive-100 bg-white">
                @foreach($items as $item)
                    <tr class="hover:bg-automotive-50/50">
                        <td class="px-4 py-3 align-top">
                            <p class="font-medium text-automotive-900">{{ $item->name }}</p>
                            @if($item->part_number)
                                <p class="mt-0.5 text-xs text-automotive-500">P/N: {{ $item->part_number }}</p>
                            @endif
                            @if($item->description)
                                <p class="mt-0.5 text-xs text-automotive-500">{{ $item->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right align-top whitespace-nowrap text-automotive-800">
                            {{ number_format($item->quantity, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-right align-top whitespace-nowrap text-automotive-800">
                            @if($item->unit_price !== null)
                                R$ {{ number_format($item->unit_price, 2, ',', '.') }}
                            @else
                                <span class="text-automotive-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right align-top whitespace-nowrap font-medium text-automotive-900">
                            @php
                                $lineTotal = $item->total_price ?? (
                                    $item->unit_price !== null ? $item->unit_price * $item->quantity : null
                                );
                            @endphp
                            @if($lineTotal !== null)
                                R$ {{ number_format($lineTotal, 2, ',', '.') }}
                            @else
                                <span class="text-automotive-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            @php
                $grandTotal = $items->sum(fn ($item) => $item->total_price ?? (
                    $item->unit_price !== null ? $item->unit_price * $item->quantity : 0
                ));
            @endphp
            @if($grandTotal > 0)
                <tfoot class="bg-automotive-50">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right font-semibold text-automotive-700">Total dos itens</td>
                        <td class="px-4 py-3 text-right font-semibold text-automotive-900 whitespace-nowrap">
                            R$ {{ number_format($grandTotal, 2, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endif

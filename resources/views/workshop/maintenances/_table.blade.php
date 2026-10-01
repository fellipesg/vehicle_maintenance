{{--
    Tabela de OS da oficina (lista "Ordens de serviço" e "OS recentes" do Início): Data · Veículo ·
    Serviço · Itens / Total · Anexos · Código do selo. Empilhada abaixo de md (x-ui.table stack):
    no celular cada OS vira um bloco com o serviço no topo.

    Variáveis:
    - $maintenances: OS com vehicle carregado e withCount(invoices, photos, items) +
      withSum('items as items_total', 'total_price').
    - $caption: nome da tabela para leitor de tela.
    - $sortable: coluna Data ordenável (?ordenar=data&direcao=), com $sort e $direction.
    - $empty: slot/HTML do estado vazio (opcional).
--}}
@php
    $sortable = $sortable ?? false;
    $money = fn (float $value): string => 'R$ '.number_format($value, 2, ',', '.');
    $plural = fn (int $count, string $one, string $many): string => $count === 1 ? "1 {$one}" : "{$count} {$many}";
@endphp

<x-ui.table :caption="$caption" stack :sort="$sortable ? ($sort ?? 'data') : null" :direction="$direction ?? 'desc'" data-workshop-maintenances-table>
    <x-slot:head>
        <tr>
            @if($sortable)
                <th data-sort="data" data-sort-default="desc">Data</th>
            @else
                <th>Data</th>
            @endif
            <th>Veículo</th>
            <th>Serviço</th>
            <th class="text-right">Itens / Total</th>
            <th>Anexos</th>
            <th>Código do selo</th>
        </tr>
    </x-slot:head>
    @foreach($maintenances as $maintenance)
        @php
            $rowVehicle = $maintenance->vehicle;
            $rowItems = (int) ($maintenance->items_count ?? 0);
            $rowTotal = (float) ($maintenance->items_total ?? 0);
            $rowInvoices = (int) ($maintenance->invoices_count ?? 0);
            $rowPhotos = (int) ($maintenance->photos_count ?? 0);
            $rowCategory = \App\Enums\ServiceCategory::labelFor($maintenance->service_category);
        @endphp
        <tr data-maintenance-row="{{ $maintenance->id }}" data-verified="{{ $maintenance->isVerified() ? '1' : '0' }}">
            <td class="whitespace-nowrap">
                <span class="flex flex-col items-end md:items-start">
                    @if($maintenance->maintenance_date)
                        <time datetime="{{ $maintenance->maintenance_date->format('Y-m-d') }}" class="tabular-nums">{{ $maintenance->maintenance_date->format('d/m/Y') }}</time>
                    @else
                        <span>—</span>
                    @endif
                    @if($maintenance->kilometers !== null)
                        <span class="text-xs text-muted-foreground tabular-nums">{{ number_format((int) $maintenance->kilometers, 0, ',', '.') }} km</span>
                    @endif
                </span>
            </td>
            <td>
                @if($rowVehicle)
                    <span class="flex flex-col items-end md:items-start">
                        <span class="text-foreground">{{ trim($rowVehicle->brand.' '.$rowVehicle->model) }}</span>
                        @if(filled($rowVehicle->license_plate))
                            <span class="font-mono text-xs tracking-wider text-muted-foreground"><span class="sr-only">Placa </span>{{ $rowVehicle->license_plate }}</span>
                        @endif
                    </span>
                @else
                    —
                @endif
            </td>
            <th scope="row" class="font-medium">
                <a href="{{ route('workshop.maintenances.show', $maintenance) }}" class="link">{{ $maintenance->maintenance_type }}</a>
                <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-normal text-muted-foreground">
                    @if($rowCategory)
                        <span>{{ $rowCategory }}</span>
                    @endif
                    @unless($maintenance->isVerified())
                        <x-ui.badge variant="declared" size="sm">{{ $maintenance->provenance_label }}</x-ui.badge>
                    @endunless
                </span>
            </th>
            <td class="text-right whitespace-nowrap">
                <span class="flex flex-col items-end">
                    <span>{{ $rowItems === 0 ? 'Sem itens' : $plural($rowItems, 'item', 'itens') }}</span>
                    @if($rowTotal > 0)
                        <span class="text-xs text-muted-foreground tabular-nums">{{ $money($rowTotal) }}</span>
                    @endif
                </span>
            </td>
            <td class="whitespace-nowrap">
                @if($rowInvoices === 0 && $rowPhotos === 0)
                    <span class="text-muted-foreground">Nenhum</span>
                @else
                    <span class="inline-flex items-center gap-3">
                        @if($rowInvoices > 0)
                            <span class="inline-flex items-center gap-1" title="{{ $plural($rowInvoices, 'nota fiscal', 'notas fiscais') }}">
                                <x-ui.icon name="document-text" class="size-4 text-muted-foreground" />
                                <span aria-hidden="true" class="tabular-nums">{{ $rowInvoices }}</span>
                                <span class="sr-only">{{ $plural($rowInvoices, 'nota fiscal', 'notas fiscais') }}</span>
                            </span>
                        @endif
                        @if($rowPhotos > 0)
                            <span class="inline-flex items-center gap-1" title="{{ $plural($rowPhotos, 'foto', 'fotos') }}">
                                <x-ui.icon name="photo" class="size-4 text-muted-foreground" />
                                <span aria-hidden="true" class="tabular-nums">{{ $rowPhotos }}</span>
                                <span class="sr-only">{{ $plural($rowPhotos, 'foto', 'fotos') }}</span>
                            </span>
                        @endif
                    </span>
                @endif
            </td>
            <td class="whitespace-nowrap">
                @if($maintenance->isVerified() && filled($maintenance->verification_code))
                    <span class="font-mono text-xs tracking-wider text-foreground">{{ $maintenance->verification_code }}</span>
                @else
                    <span class="text-muted-foreground">Sem selo</span>
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.table>

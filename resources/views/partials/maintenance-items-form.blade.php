{{--
    Etapa "Peças e serviços" do formulário de OS da oficina: lista de itens com adicionar e remover
    (resources/js/maintenance-items.js), total do item e total dos itens ao vivo, e a garantia de
    cada item. A lista pode ficar vazia: com uma NF-e anexada, os itens do XML entram na OS.

    Variáveis: $items (itens da OS, na edição), $fieldPrefix ('items'), $itemTemplates (modelos de
    garantia por item, ativos), $maintenanceDate (Y-m-d) e $sectionNumber (número da etapa).
--}}
@php
    $items = $items ?? collect();
    $fieldPrefix = $fieldPrefix ?? 'items';
    $itemTemplates = $itemTemplates ?? collect();
    $maintenanceDate = $maintenanceDate ?? null;

    // Depois de um erro de validação, remonta as linhas que a pessoa enviou (inclusive as novas).
    if (session()->hasOldInput()) {
        $oldRows = old($fieldPrefix);
        $rows = collect(is_array($oldRows) ? $oldRows : [])
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => isset($row['id']) ? $items->firstWhere('id', (int) $row['id']) : null)
            ->values();
    } else {
        $rows = $items->values();
    }

    $itemsTotal = $rows->keys()->sum(function (int $rowIndex) use ($rows, $fieldPrefix): float {
        $item = $rows[$rowIndex];
        $price = old("{$fieldPrefix}.{$rowIndex}.unit_price", $item?->unit_price);
        $quantity = old("{$fieldPrefix}.{$rowIndex}.quantity", $item?->quantity ?? 1);

        return is_numeric($price) && is_numeric($quantity) ? (float) $price * (int) $quantity : 0.0;
    });
@endphp

<x-ui.form-section
    id="secao-itens"
    :number="$sectionNumber ?? null"
    title="Peças e serviços"
    description="Registre as peças trocadas e os serviços feitos, com a garantia de cada item quando houver. Deixe a lista vazia para importar os itens da NF-e anexada."
    data-maintenance-items
    data-field-prefix="{{ $fieldPrefix }}"
>
    @error($fieldPrefix)
        <p id="{{ $fieldPrefix }}-error" class="flex items-start gap-1.5 text-sm text-danger"><x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" /><span><span class="sr-only">Erro: </span>{{ $message }}</span></p>
    @enderror

    <p class="rounded-control border border-dashed border-border-strong px-4 py-6 text-center text-sm text-muted-foreground" data-items-empty @if($rows->isNotEmpty()) hidden @endif>
        Nenhuma peça ou serviço adicionado.
    </p>

    <div class="space-y-4" data-items-list>
        @foreach($rows as $index => $item)
            @include('partials.maintenance-item-row', [
                'index' => $index,
                'item' => $item,
                'fieldPrefix' => $fieldPrefix,
                'itemTemplates' => $itemTemplates,
                'maintenanceDate' => $maintenanceDate,
            ])
        @endforeach
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.button variant="secondary" icon="plus" data-add-item>Adicionar peça ou serviço</x-ui.button>
        <p class="text-sm text-muted-foreground sm:text-right" data-items-total-wrapper @if($rows->isEmpty()) hidden @endif>
            Total dos itens
            <span class="ml-1 text-base font-semibold text-foreground tabular-nums" data-items-total>R$ {{ number_format($itemsTotal, 2, ',', '.') }}</span>
        </p>
    </div>

    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-items-status></p>

    <template data-item-template>
        @include('partials.maintenance-item-row', [
            'index' => '__INDEX__',
            'item' => null,
            'fieldPrefix' => $fieldPrefix,
            'itemTemplates' => $itemTemplates,
            'maintenanceDate' => null,
        ])
    </template>
</x-ui.form-section>

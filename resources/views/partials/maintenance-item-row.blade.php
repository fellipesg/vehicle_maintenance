@php
    $index = $index ?? 0;
    $fieldPrefix = $fieldPrefix ?? 'items';
    $namePrefix = "{$fieldPrefix}[{$index}]";
    $itemTemplates = $itemTemplates ?? collect();
    $selectedTemplateId = old("{$fieldPrefix}.{$index}.warranty_template_id", $item?->warranty?->warranty_template_id);
@endphp

<div class="rounded-lg border border-automotive-200 p-4" data-item-row data-index="{{ $index }}">
    <div class="mb-3 flex items-center justify-between gap-2">
        <p class="font-medium text-automotive-900" data-item-title>Item {{ is_numeric($index) ? ((int) $index + 1) : 1 }}</p>
        <button type="button" data-remove-item class="text-sm text-red-600 hover:underline">Remover</button>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="form-label">Nome da peça ou serviço *</label>
            <input type="text" name="{{ $namePrefix }}[name]" value="{{ old("{$fieldPrefix}.{$index}.name", $item?->name) }}" required class="form-input" placeholder="Ex: Pastilha de freio dianteira">
        </div>
        <div>
            <label class="form-label">Quantidade *</label>
            <input type="number" name="{{ $namePrefix }}[quantity]" value="{{ old("{$fieldPrefix}.{$index}.quantity", $item?->quantity ?? 1) }}" min="1" required class="form-input">
        </div>
        <div>
            <label class="form-label">Preço unitário (R$)</label>
            <input type="number" name="{{ $namePrefix }}[unit_price]" value="{{ old("{$fieldPrefix}.{$index}.unit_price", $item?->unit_price) }}" min="0" step="0.01" class="form-input">
        </div>
        <div>
            <label class="form-label">Nº da peça</label>
            <input type="text" name="{{ $namePrefix }}[part_number]" value="{{ old("{$fieldPrefix}.{$index}.part_number", $item?->part_number) }}" class="form-input">
        </div>
        <div class="sm:col-span-2">
            <label class="form-label">Descrição</label>
            <textarea name="{{ $namePrefix }}[description]" rows="2" class="form-input">{{ old("{$fieldPrefix}.{$index}.description", $item?->description) }}</textarea>
        </div>
        @if($itemTemplates->isNotEmpty())
            <div class="sm:col-span-2">
                <label class="form-label">Garantia do item</label>
                <select name="{{ $namePrefix }}[warranty_template_id]" class="form-input">
                    <option value="">Sem garantia</option>
                    @foreach($itemTemplates as $template)
                        <option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id)>
                            {{ $template->name }} ({{ $template->duration_days }} dias)
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>
</div>

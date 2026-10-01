{{--
    Uma linha da lista de peças e serviços da OS (partials/maintenance-items-form). Os ids seguem o
    padrão de App\Support\FormField::controlId (items[0][name] vira items_0_name), então o resumo de
    erros (<x-ui.form-errors>) leva direto ao campo. resources/js/maintenance-items.js refaz nomes,
    ids, rótulos e aria-describedby ao adicionar ou remover linhas, soma os totais e mostra a data
    "Válida até" da garantia escolhida.

    Variáveis: $index (número ou '__INDEX__' na linha modelo), $item (MaintenanceItem ou null),
    $fieldPrefix ('items'), $itemTemplates (modelos de garantia por item, ativos) e
    $maintenanceDate (Y-m-d da OS, para a data "Válida até").
--}}
@php
    use App\Support\FormField;

    $index = $index ?? 0;
    $fieldPrefix = $fieldPrefix ?? 'items';
    $maintenanceDate = $maintenanceDate ?? null;
    $namePrefix = "{$fieldPrefix}[{$index}]";
    $idPrefix = "{$fieldPrefix}_{$index}";
    $oldPrefix = "{$fieldPrefix}.{$index}";
    $itemNumber = is_numeric($index) ? ((int) $index + 1) : 1;
    $itemTemplates = $itemTemplates ?? collect();
    $itemId = $item?->id;
    $issuedWarranty = $item?->warranty;
    $issuedTemplateId = $issuedWarranty?->warranty_template_id;
    $selectedTemplateId = old("{$oldPrefix}.warranty_template_id", $issuedTemplateId);
    // Modelo desativado depois da emissão: continua na lista desta linha para a garantia não sumir ao salvar.
    $keepsInactiveTemplate = $issuedTemplateId !== null && ! $itemTemplates->contains('id', $issuedTemplateId);
    $showsWarranty = $itemTemplates->isNotEmpty() || $issuedWarranty;
    $rowError = fn (string $field): ?string => is_numeric($index) ? FormField::error($errors ?? null, "{$oldPrefix}.{$field}") : null;
    $messageId = fn (string $key): string => "{$idPrefix}_{$key}";
    $errorId = fn (string $field): ?string => $rowError($field) !== null ? $messageId("{$field}-error") : null;
    $quantity = old("{$oldPrefix}.quantity", $item?->quantity ?? 1);
    $unitPrice = old("{$oldPrefix}.unit_price", $item?->unit_price);
    $lineTotal = is_numeric($unitPrice) && is_numeric($quantity) ? (float) $unitPrice * (int) $quantity : null;
    $money = fn (float $value): string => 'R$ '.number_format($value, 2, ',', '.');
    $untilLabel = function (?int $days) use ($maintenanceDate): ?string {
        if ($days === null || blank($maintenanceDate)) {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse($maintenanceDate)->startOfDay()->addDays($days)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    };
    $selectedDuration = match (true) {
        blank($selectedTemplateId) => null,
        $keepsInactiveTemplate && (string) $selectedTemplateId === (string) $issuedTemplateId => (int) $issuedWarranty->duration_days,
        default => ($template = $itemTemplates->firstWhere('id', (int) $selectedTemplateId)) ? (int) $template->duration_days : null,
    };
    $selectedUntil = $untilLabel($selectedDuration);
@endphp

<div class="rounded-control border border-border bg-surface p-4" role="group" aria-labelledby="{{ $messageId('title') }}"
     data-item-row data-index="{{ $index }}" @if($issuedWarranty) data-has-warranty @endif>
    <div class="mb-3 flex items-center justify-between gap-2">
        <p id="{{ $messageId('title') }}" class="font-semibold text-foreground" data-item-title>Item {{ $itemNumber }}</p>
        <button type="button" data-remove-item aria-label="Remover item {{ $itemNumber }}"
                class="-mr-2 inline-flex size-10 shrink-0 items-center justify-center rounded-control text-danger transition-colors duration-fast ease-smooth-out hover:bg-danger-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none">
            <x-ui.icon name="trash" class="size-5" />
        </button>
    </div>

    @if($itemId)
        <input type="hidden" id="{{ $messageId('id') }}" name="{{ $namePrefix }}[id]" value="{{ $itemId }}" data-item-field="id">
    @endif

    <div class="grid gap-x-3 gap-y-4 sm:grid-cols-[minmax(0,1fr)_5.5rem_12.5rem] sm:items-start">
        <div class="grid gap-1.5">
            <x-ui.label :for="$messageId('name')" required data-item-label="name">Nome da peça ou serviço</x-ui.label>
            <x-ui.input :id="$messageId('name')" :name="$namePrefix.'[name]'" :value="$item?->name" data-item-field="name"
                        required maxlength="255" placeholder="Ex.: Pastilha de freio dianteira"
                        :aria-describedby="$errorId('name')" />
            @if($rowError('name'))
                <p id="{{ $errorId('name') }}" data-item-message="name-error" class="flex items-start gap-1.5 text-sm text-danger"><x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" /><span><span class="sr-only">Erro: </span>{{ $rowError('name') }}</span></p>
            @endif
        </div>

        <div class="grid content-start gap-1.5">
            <x-ui.label :for="$messageId('quantity')" required data-item-label="quantity">Quantidade</x-ui.label>
            <x-ui.input type="number" :id="$messageId('quantity')" :name="$namePrefix.'[quantity]'" :value="$item?->quantity ?? 1" data-item-field="quantity"
                        min="1" step="1" inputmode="numeric" required class="tabular-nums"
                        :aria-describedby="$errorId('quantity')" />
            @if($rowError('quantity'))
                <p id="{{ $errorId('quantity') }}" data-item-message="quantity-error" class="flex items-start gap-1.5 text-sm text-danger"><x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" /><span><span class="sr-only">Erro: </span>{{ $rowError('quantity') }}</span></p>
            @endif
        </div>

        <div class="grid content-start gap-1.5">
            <x-ui.label :for="$messageId('unit_price')" optional data-item-label="unit_price">Preço unitário</x-ui.label>
            <x-ui.input-group>
                <x-slot:leading>R$</x-slot:leading>
                <x-ui.input type="number" :id="$messageId('unit_price')" :name="$namePrefix.'[unit_price]'" :value="$item?->unit_price" data-item-field="unit_price"
                            min="0" step="0.01" inputmode="decimal" placeholder="0,00" class="tabular-nums"
                            :aria-describedby="$errorId('unit_price')" />
            </x-ui.input-group>
            @if($rowError('unit_price'))
                <p id="{{ $errorId('unit_price') }}" data-item-message="unit_price-error" class="flex items-start gap-1.5 text-sm text-danger"><x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" /><span><span class="sr-only">Erro: </span>{{ $rowError('unit_price') }}</span></p>
            @endif
        </div>

        <div class="grid content-start gap-1.5 sm:col-span-2">
            <x-ui.label :for="$messageId('part_number')" optional data-item-label="part_number">Código da peça</x-ui.label>
            <x-ui.input :id="$messageId('part_number')" :name="$namePrefix.'[part_number]'" :value="$item?->part_number" data-item-field="part_number"
                        maxlength="100" autocomplete="off"
                        :aria-describedby="$errorId('part_number')" />
            @if($rowError('part_number'))
                <p id="{{ $errorId('part_number') }}" data-item-message="part_number-error" class="flex items-start gap-1.5 text-sm text-danger"><x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" /><span><span class="sr-only">Erro: </span>{{ $rowError('part_number') }}</span></p>
            @endif
        </div>

        <p class="flex items-baseline justify-between gap-2 text-sm text-muted-foreground sm:flex-col sm:items-end sm:gap-0.5 sm:self-end sm:pb-2">
            <span>Total do item</span>
            <span class="text-base font-semibold text-foreground tabular-nums" data-item-line-total>{{ $lineTotal !== null ? $money($lineTotal) : '—' }}</span>
        </p>

        <div class="grid gap-1.5 sm:col-span-3">
            <x-ui.label :for="$messageId('description')" optional data-item-label="description">Descrição</x-ui.label>
            <x-ui.textarea :id="$messageId('description')" :name="$namePrefix.'[description]'" :value="$item?->description" data-item-field="description" rows="2" class="min-h-16" />
        </div>

        @if($showsWarranty)
            <div class="grid gap-1.5 sm:col-span-3">
                <x-ui.label :for="$messageId('warranty_template_id')" optional data-item-label="warranty_template_id">Garantia do item</x-ui.label>
                @if($issuedWarranty)
                    <p class="text-sm text-foreground">Garantia emitida: {{ $issuedWarranty->name }} · válida até {{ $issuedWarranty->ends_at->format('d/m/Y') }}</p>
                @endif
                <x-ui.select :id="$messageId('warranty_template_id')" :name="$namePrefix.'[warranty_template_id]'" data-item-field="warranty_template_id" data-warranty-select
                             :aria-describedby="FormField::describedBy($issuedWarranty ? $messageId('warranty_template_id-hint') : null, $messageId('warranty_template_id-until'), $errorId('warranty_template_id'))">
                    <option value="">Sem garantia</option>
                    @if($keepsInactiveTemplate)
                        <option value="{{ $issuedTemplateId }}" @selected((string) $selectedTemplateId === (string) $issuedTemplateId) data-duration-days="{{ (int) $issuedWarranty->duration_days }}">{{ $issuedWarranty->name }} (modelo desativado, mantido nesta OS)</option>
                    @endif
                    @foreach($itemTemplates as $template)
                        <option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id) data-duration-days="{{ (int) $template->duration_days }}">{{ $template->name }} ({{ $template->duration_days }} dias)</option>
                    @endforeach
                </x-ui.select>
                <p id="{{ $messageId('warranty_template_id-until') }}" data-item-message="warranty_template_id-until" data-warranty-until class="text-sm text-muted-foreground empty:hidden">@if($selectedUntil)Válida até <span class="font-medium text-foreground tabular-nums">{{ $selectedUntil }}</span>@endif</p>
                @if($issuedWarranty)
                    <p id="{{ $messageId('warranty_template_id-hint') }}" data-item-message="warranty_template_id-hint" class="text-sm text-muted-foreground">Trocar o modelo ou escolher "Sem garantia" encerra a garantia emitida quando você salvar.</p>
                @endif
                @if($rowError('warranty_template_id'))
                    <p id="{{ $errorId('warranty_template_id') }}" data-item-message="warranty_template_id-error" class="flex items-start gap-1.5 text-sm text-danger"><x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" /><span><span class="sr-only">Erro: </span>{{ $rowError('warranty_template_id') }}</span></p>
                @endif
            </div>
        @endif
    </div>
</div>

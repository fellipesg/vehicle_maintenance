@php
    $items = $items ?? collect();
    $fieldPrefix = $fieldPrefix ?? 'items';
@endphp

<div class="space-y-4" id="maintenance-items-form" data-field-prefix="{{ $fieldPrefix }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-lg font-semibold">Peças e serviços</h2>
        <button type="button" id="add-maintenance-item" class="btn-secondary text-sm">+ Adicionar item</button>
    </div>
    <p class="text-sm text-automotive-600">Registre peças trocadas ou serviços executados. Selecione um template de garantia quando aplicável.</p>

    <div id="maintenance-items-list" class="space-y-4">
        @forelse($items as $index => $item)
            @include('partials.maintenance-item-row', [
                'index' => $index,
                'item' => $item,
                'fieldPrefix' => $fieldPrefix,
                'itemTemplates' => $itemTemplates ?? collect(),
            ])
        @empty
            @include('partials.maintenance-item-row', [
                'index' => 0,
                'item' => null,
                'fieldPrefix' => $fieldPrefix,
                'itemTemplates' => $itemTemplates ?? collect(),
            ])
        @endforelse
    </div>
</div>

<template id="maintenance-item-row-template">
    @include('partials.maintenance-item-row', [
        'index' => '__INDEX__',
        'item' => null,
        'fieldPrefix' => $fieldPrefix,
        'itemTemplates' => $itemTemplates ?? collect(),
    ])
</template>

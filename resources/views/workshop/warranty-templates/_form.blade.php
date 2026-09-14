@php
    $locked = $templatesLocked ?? false;
    $readonly = $locked && isset($template);
@endphp

@if($workshop->logoUrl())
    <div class="mb-4 flex items-center gap-3 rounded border border-automotive-100 bg-automotive-50 p-3">
        <img src="{{ $workshop->logoUrl() }}" alt="Logo da oficina" class="h-12 w-auto object-contain">
        <p class="text-sm text-automotive-600">Logo da oficina usada nos termos de garantia e PDFs.</p>
    </div>
@endif

@if($readonly)
    <div class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        Não é possível editar templates enquanto houver garantias vigentes desta oficina. Você pode visualizar os dados, alterar apenas o status ativo ou criar um novo template.
    </div>
@endif

<div>
    <label for="name" class="form-label">Nome do template *</label>
    <input type="text" name="name" id="name" value="{{ old('name', $template->name ?? '') }}" required @disabled($readonly) class="form-input @if($readonly) bg-automotive-100 @endif">
    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="body" class="form-label">Termo de garantia *</label>
    <textarea name="body" id="body" rows="8" required @disabled($readonly) class="form-input @if($readonly) bg-automotive-100 @endif">{{ old('body', $template->body ?? '') }}</textarea>
    @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="duration_days" class="form-label">Duração (dias) *</label>
        <input type="number" name="duration_days" id="duration_days" min="1" max="3650" value="{{ old('duration_days', $template->duration_days ?? 90) }}" required @disabled($readonly) class="form-input @if($readonly) bg-automotive-100 @endif">
        @error('duration_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="scope" class="form-label">Escopo *</label>
        <select name="scope" id="scope" required @disabled($readonly) class="form-input @if($readonly) bg-automotive-100 @endif">
            <option value="order" @selected(old('scope', $template?->scope?->value ?? 'order') === 'order')>Ordem de serviço (geral)</option>
            <option value="item" @selected(old('scope', $template?->scope?->value ?? '') === 'item')>Item da OS</option>
        </select>
        @error('scope')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $template->is_active ?? true)) class="rounded border-automotive-300">
    <label for="is_active" class="text-sm">Template ativo (disponível para novas OS)</label>
</div>
@error('is_active')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

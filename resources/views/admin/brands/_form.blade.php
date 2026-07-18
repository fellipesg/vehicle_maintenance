<div class="grid gap-4">
    <div>
        <label for="name" class="form-label">Nome da marca *</label>
        <input type="text" name="name" id="name"
               value="{{ old('name', $brand->name ?? '') }}" required
               class="form-input" placeholder="Ex: Mercedes-Benz">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1"
               class="rounded border-automotive-300 text-wrench-600 focus:ring-wrench-500"
               @checked(old('is_active', $brand->is_active ?? true))>
        <label for="is_active" class="text-sm text-automotive-700">Marca ativa (visível nos formulários)</label>
    </div>
</div>

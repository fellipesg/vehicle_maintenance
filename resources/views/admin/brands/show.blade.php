@extends('layouts.app')

@section('title', $brand->name)

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.brands.index') }}" class="text-sm text-wrench-600 hover:underline">← Todas as marcas</a>
            <h1 class="mt-2 text-3xl font-bold">{{ $brand->name }}</h1>
            <p class="text-automotive-600">
                {{ $brand->models->count() }} modelos
                @if(! $brand->is_active)
                    · <span class="text-orange-600">Marca inativa</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.brands.edit', $brand) }}" class="btn-secondary">Editar marca</a>
    </div>

    <div class="grid gap-8 lg:grid-cols-2">
        <div class="card">
            <h2 class="mb-4 text-lg font-semibold">Adicionar modelo</h2>
            <form method="POST" action="{{ route('admin.brands.models.store', $brand) }}" class="space-y-4">
                @csrf
                <div>
                    <label for="name" class="form-label">Nome do modelo *</label>
                    <input type="text" name="name" id="name" required class="form-input"
                           value="{{ old('name') }}" placeholder="Ex: C 180">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="model_is_active" value="1" checked
                           class="rounded border-automotive-300 text-wrench-600 focus:ring-wrench-500">
                    <label for="model_is_active" class="text-sm text-automotive-700">Modelo ativo</label>
                </div>
                <button type="submit" class="btn-primary">Adicionar modelo</button>
            </form>
        </div>

        <div class="card">
            <h2 class="mb-4 text-lg font-semibold">Modelos cadastrados</h2>
            @forelse($brand->models as $model)
                <div class="mb-4 rounded-lg border border-automotive-100 p-4">
                    <form method="POST" action="{{ route('admin.models.update', $model) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-wrap items-start gap-3">
                            <input type="text" name="name" value="{{ old('name', $model->name) }}" required
                                   class="form-input flex-1">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1"
                                       class="rounded border-automotive-300 text-wrench-600"
                                       @checked($model->is_active)>
                                Ativo
                            </label>
                        </div>
                        <div class="flex items-center gap-4">
                            <button type="submit" class="text-sm text-wrench-600 hover:underline">Salvar</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.models.destroy', $model) }}" class="mt-2"
                          onsubmit="return confirm('Remover o modelo {{ $model->name }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:underline">Excluir</button>
                    </form>
                </div>
            @empty
                <p class="text-automotive-500">Nenhum modelo cadastrado para esta marca.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

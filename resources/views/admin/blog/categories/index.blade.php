@extends('layouts.admin')

@section('title', 'Categorias do blog')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.blog.index') }}" class="text-sm text-wrench-600 hover:underline">← Voltar para o blog</a>
        <h1 class="mt-2 text-3xl font-bold">Categorias do blog</h1>
        <p class="text-automotive-600">Agrupam os artigos na listagem pública</p>
    </div>

    <form method="POST" action="{{ route('admin.blog.categories.store') }}" class="card mb-6 space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="form-label">Nome *</label>
                <input type="text" name="name" id="name" required maxlength="80"
                       value="{{ old('name') }}" class="form-input" placeholder="Ex: Manutenção">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="form-label">Descrição</label>
                <input type="text" name="description" id="description" maxlength="255"
                       value="{{ old('description') }}" class="form-input" placeholder="Aparece no topo da categoria">
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <button type="submit" class="btn-primary">Adicionar categoria</button>
    </form>

    <div class="card overflow-hidden !p-0">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-automotive-200 bg-automotive-50">
                <tr>
                    <th class="px-4 py-3 font-semibold">Categoria</th>
                    <th class="px-4 py-3 font-semibold">Posts</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 text-right font-semibold">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr class="border-b border-automotive-100 last:border-0">
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.blog.categories.update', $category) }}"
                                  id="category-{{ $category->id }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $category->name }}" required maxlength="80"
                                       class="form-input sm:w-48">
                                <input type="text" name="description" value="{{ $category->description }}" maxlength="255"
                                       class="form-input sm:w-72" placeholder="Descrição">
                                <label class="flex items-center gap-2 text-xs text-automotive-600">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active)
                                           class="rounded border-automotive-300 text-wrench-600 focus:ring-wrench-500">
                                    Ativa
                                </label>
                            </form>
                            <span class="mt-1 block text-xs text-automotive-500">/blog/categoria/{{ $category->slug }}</span>
                        </td>
                        <td class="px-4 py-3 text-automotive-600">{{ $category->posts_count }}</td>
                        <td class="px-4 py-3">
                            @if($category->is_active)
                                <span class="badge badge-orange">Ativa</span>
                            @else
                                <span class="badge badge-blue">Inativa</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="submit" form="category-{{ $category->id }}" class="text-wrench-600 hover:underline">
                                Salvar
                            </button>
                            <span class="text-automotive-300">·</span>
                            <form method="POST" action="{{ route('admin.blog.categories.destroy', $category) }}" class="inline"
                                  onsubmit="return confirm('Remover esta categoria? Os posts ficam sem categoria.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Remover</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-automotive-500">
                            Nenhuma categoria cadastrada.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

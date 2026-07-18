@extends('layouts.app')

@section('title', 'Editar marca')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.brands.show', $brand) }}" class="text-sm text-wrench-600 hover:underline">← Voltar para {{ $brand->name }}</a>
        <h1 class="mt-2 text-3xl font-bold">Editar marca</h1>
    </div>

    <form method="POST" action="{{ route('admin.brands.update', $brand) }}" class="card mb-4 space-y-4">
        @csrf
        @method('PUT')
        @include('admin.brands._form', ['brand' => $brand])
        <button type="submit" class="btn-primary">Salvar alterações</button>
    </form>

    <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}"
          onsubmit="return confirm('Remover esta marca e todos os modelos?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-sm text-red-600 hover:underline">Excluir marca</button>
    </form>
</div>
@endsection

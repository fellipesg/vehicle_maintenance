@extends('layouts.app')

@section('title', 'Confirmar dados do CRLV-e')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <span class="badge badge-green mb-2">🏪 Garagem</span>
    <h1 class="mb-2 text-3xl font-bold">Confirmar veículo do estoque</h1>
    <p class="mb-6 text-sm text-automotive-600">
        Dados importados de <strong>{{ $sourceFile ?? 'CRLV-e' }}</strong>.
        Revise as informações antes de adicionar ao estoque.
    </p>

    @include('user.vehicles._preview-import-summary', ['preview' => $preview])

    <form method="POST" action="{{ route($storeRoute) }}" class="card space-y-4">
        @csrf
        @include('user.vehicles._form', [
            'catalog' => $catalog,
            'vehicle' => (object) $preview,
        ])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Confirmar e adicionar ao estoque</button>
            <a href="{{ route($createRoute) }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

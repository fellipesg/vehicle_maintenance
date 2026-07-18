@extends('layouts.app')

@section('title', 'Confirmar dados do CRLV-e')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-2 text-3xl font-bold">🚗 Confirmar cadastro</h1>
    <p class="mb-6 text-sm text-automotive-600">
        Dados importados de <strong>{{ $sourceFile ?? 'CRLV-e' }}</strong>.
        Revise as informações antes de salvar.
    </p>

    @include('user.vehicles._preview-import-summary', ['preview' => $preview])

    <form method="POST" action="{{ route($storeRoute) }}" class="card space-y-4">
        @csrf
        <input type="hidden" name="crlv_verification_token" value="{{ old('crlv_verification_token', $preview['crlv_verification_token'] ?? '') }}">
        @include('user.vehicles._form', [
            'catalog' => $catalog,
            'vehicle' => (object) $preview,
        ])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Confirmar e salvar veículo</button>
            <a href="{{ route($createRoute) }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

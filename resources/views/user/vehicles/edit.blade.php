@extends('layouts.app')

@section('title', 'Editar Veículo')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-6 text-3xl font-bold">✏️ Editar Veículo</h1>

    @include('partials.crlv-import', [
        'importRoute' => route('user.vehicles.import-crlv.edit', $vehicle),
        'inputId' => 'edit_crlv',
        'description' => 'Envie o CRLV-e digital deste veículo (mesmo RENAVAM). Os dados serão atualizados automaticamente, inclusive o número do CRV.',
        'submitLabel' => 'Importar e atualizar com CRLV-e',
    ])

    <div class="my-6 flex items-center gap-3 text-sm text-automotive-500">
        <span class="h-px flex-1 bg-automotive-200"></span>
        <span>ou edite manualmente</span>
        <span class="h-px flex-1 bg-automotive-200"></span>
    </div>

    <form method="POST" action="{{ route('user.vehicles.update', $vehicle) }}" class="card space-y-4">
        @csrf
        @method('PUT')
        @include('user.vehicles._form', ['vehicle' => $vehicle, 'catalog' => $catalog])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Atualizar</button>
            <a href="{{ route('user.vehicles.show', $vehicle) }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

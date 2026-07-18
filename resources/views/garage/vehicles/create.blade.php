@extends('layouts.app')

@section('title', 'Adicionar ao Estoque')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <span class="badge badge-green mb-2">🏪 Garagem</span>
    <h1 class="mb-6 text-3xl font-bold">Adicionar Veículo ao Estoque</h1>

    @include('partials.crlv-import', ['importRoute' => route('garage.vehicles.import-crlv')])

    <div class="my-6 flex items-center gap-3 text-sm text-automotive-500">
        <span class="h-px flex-1 bg-automotive-200"></span>
        <span>ou preencha manualmente</span>
        <span class="h-px flex-1 bg-automotive-200"></span>
    </div>

    <form method="POST" action="{{ route('garage.vehicles.store') }}" class="card space-y-4">
        @csrf
        @include('user.vehicles._form', ['catalog' => $catalog])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Adicionar ao estoque</button>
            <a href="{{ route('garage.vehicles.index') }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

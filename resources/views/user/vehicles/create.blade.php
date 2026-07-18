@extends('layouts.app')

@section('title', 'Novo Veículo')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-6 text-3xl font-bold">🚗 Cadastrar Veículo</h1>

    @include('partials.crlv-import', ['importRoute' => route('user.vehicles.import-crlv')])

    <div class="my-6 flex items-center gap-3 text-sm text-automotive-500">
        <span class="h-px flex-1 bg-automotive-200"></span>
        <span>Recomendado: importe o CRLV-e acima</span>
        <span class="h-px flex-1 bg-automotive-200"></span>
    </div>

    <p class="text-center text-sm text-automotive-500">
        Veículo já cadastrado?
        <a href="{{ route('user.vehicles.claim') }}" class="text-wrench-600 hover:underline">Vincular com CRLV-e</a>
    </p>

    <p class="mt-4 text-center">
        <a href="{{ route('user.vehicles.index') }}" class="btn-secondary">Voltar</a>
    </p>
</div>
@endsection

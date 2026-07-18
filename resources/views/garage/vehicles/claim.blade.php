@extends('layouts.app')

@section('title', 'Vincular veículo ao estoque')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <span class="badge badge-green mb-2">🏪 Garagem</span>
    <h1 class="mb-2 text-3xl font-bold">Vincular veículo ao estoque</h1>
    <p class="mb-6 text-sm text-automotive-600">
        Envie o <strong>CRLV-e digital</strong> do veículo já cadastrado no sistema.
    </p>

    @include('partials.crlv-import', [
        'importRoute' => route('garage.vehicles.claim.import-crlv'),
        'inputId' => 'claim_crlv',
    ])

    <p class="mt-6 text-center text-sm text-automotive-500">
        <a href="{{ route('garage.vehicles.create') }}" class="text-wrench-600 hover:underline">Cadastrar veículo novo</a>
    </p>
</div>
@endsection

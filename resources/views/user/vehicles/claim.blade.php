@extends('layouts.app')

@section('title', 'Vincular veículo existente')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-2 text-3xl font-bold">Vincular veículo</h1>
    <p class="mb-6 text-sm text-automotive-600">
        Este veículo já está cadastrado no sistema. Envie o <strong>CRLV-e digital</strong> (exercício {{ date('Y') - 1 }} ou {{ date('Y') }}) para vincular à sua conta.
    </p>

    @include('partials.crlv-import', [
        'importRoute' => $claimImportRoute,
        'inputId' => 'claim_crlv',
    ])

    <p class="mt-6 text-center text-sm text-automotive-500">
        Primeiro cadastro?
        <a href="{{ route($createRoute) }}" class="text-wrench-600 hover:underline">Cadastrar veículo novo</a>
    </p>
</div>
@endsection

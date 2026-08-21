@extends('layouts.app')

@section('title', 'Novo Veículo')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-6 text-3xl font-bold">🚗 Cadastrar Veículo</h1>

    @include('partials.crlv-import', ['importRoute' => route('user.vehicles.import-crlv')])

    <div class="my-6 flex items-center gap-3 text-sm text-automotive-500">
        <span class="h-px flex-1 bg-automotive-200"></span>
        <span>ou cadastre manualmente</span>
        <span class="h-px flex-1 bg-automotive-200"></span>
    </div>

    <form method="POST" action="{{ route('user.vehicles.store') }}" class="card space-y-4">
        @csrf
        <h2 class="text-lg font-semibold text-automotive-800">Preencher dados manualmente</h2>
        <p class="text-sm text-automotive-500">
            Informe placa, RENAVAM, CRV e demais dados do veículo. O CRLV-e continua sendo a opção recomendada quando disponível.
        </p>
        @error('vehicle')
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
        @error('crlv')
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
        @include('user.vehicles._form', ['catalog' => $catalog, 'vehicle' => new \App\Models\Vehicle()])
        <x-terms-scroll-accept class="mt-2" />
        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="btn-primary" data-terms-submit disabled>Cadastrar veículo</button>
            <a href="{{ route('user.vehicles.index') }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-automotive-500">
        Veículo já cadastrado?
        <a href="{{ route('user.vehicles.claim') }}" class="text-wrench-600 hover:underline">Vincular com CRLV-e</a>
    </p>
</div>
@endsection

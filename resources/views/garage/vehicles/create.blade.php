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

    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <strong>Veículo em consignação?</strong> Importe o CRLV-e acima. A consignação só pode ser declarada
        a partir do documento, porque é ele que identifica o proprietário do veículo.
    </div>

    <form method="POST" action="{{ route('garage.vehicles.store') }}" class="card space-y-4">
        @csrf
        @error('crlv')
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
        @include('user.vehicles._form', ['catalog' => $catalog])
        <x-terms-scroll-accept class="mt-2" />
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary" data-terms-submit disabled>Adicionar ao estoque</button>
            <a href="{{ route('garage.vehicles.index') }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

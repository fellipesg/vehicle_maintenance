@extends('layouts.app')

@section('title', 'Confirmar vinculação')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-2 text-3xl font-bold">Confirmar vinculação</h1>
    <p class="mb-6 text-sm text-automotive-600">
        Veículo já cadastrado: <strong>{{ $vehicle->brand }} {{ $vehicle->model }}</strong> ({{ $vehicle->license_plate }}).
        O histórico de manutenções será associado à sua conta após a confirmação.
    </p>

    @include('user.vehicles._preview-import-summary', ['preview' => $preview])

    <div class="card mt-6">
        <p class="text-sm text-automotive-700">
            <strong>{{ $vehicle->maintenances()->count() }}</strong> manutenção(ões) já registrada(s) para este veículo.
        </p>

        <form method="POST" action="{{ route($claimStoreRoute) }}" class="mt-4">
            @csrf
            <input type="hidden" name="crlv_verification_token" value="{{ $preview['crlv_verification_token'] ?? '' }}">
            <div class="flex gap-3">
                <button type="submit" class="btn-primary">Confirmar vinculação</button>
                <a href="{{ route($claimRoute) }}" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

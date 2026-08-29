@extends('layouts.app')

@section('title', 'Meus Veículos')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8" data-api-page="vehicles-index">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold">🚗 Meus Veículos</h1>
        <a href="{{ route('user.vehicles.create') }}" class="btn-primary">+ Novo Veículo</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-vehicles-grid>
        <div class="card col-span-full text-center text-automotive-500">Carregando veículos...</div>
    </div>
</div>
@endsection

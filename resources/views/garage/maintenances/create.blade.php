@extends('layouts.app')

@section('title', 'Nova Revisão')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <span class="badge badge-green mb-2">🏪 Garagem</span>
    <h1 class="mb-6 text-3xl font-bold">Registrar Revisão Pré-Venda</h1>

    <form method="POST" action="{{ route('garage.maintenances.store') }}" enctype="multipart/form-data" class="card space-y-4">
        @csrf
        @include('partials.maintenance-form', ['vehicles' => $vehicles, 'workshops' => $workshops])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Registrar revisão</button>
            <a href="{{ route('garage.maintenances.index') }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Nova Manutenção')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="mb-6 text-3xl font-bold">🔧 Nova Ordem de Serviço</h1>

    @if(!$workshop)
        <div class="card text-center">
            <p class="text-automotive-500">Cadastre sua oficina antes de registrar serviços.</p>
            <a href="{{ route('workshop.profile.create') }}" class="btn-primary mt-4">Cadastrar oficina</a>
        </div>
    @else
        <form method="POST" action="{{ route('workshop.maintenances.store') }}" enctype="multipart/form-data" class="card space-y-4">
            @csrf
            @include('partials.workshop-maintenance-form', ['vehicle' => $vehicle, 'licensePlate' => $licensePlate])
            @include('partials.maintenance-items-form', ['itemTemplates' => $itemTemplates ?? collect()])
            @include('partials.maintenance-photos-form')
            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn-primary">Registrar OS</button>
                <a href="{{ route('workshop.maintenances.index') }}" class="btn-secondary">Cancelar</a>
            </div>
        </form>

    @endif
</div>
@endsection

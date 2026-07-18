@extends('layouts.app')

@section('title', 'Nova Manutenção')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-6 text-3xl font-bold">🔧 Registrar Manutenção</h1>

    <form method="POST" action="{{ route('user.maintenances.store') }}" enctype="multipart/form-data" class="card space-y-4">
        @csrf
        @include('partials.maintenance-form', ['vehicles' => $vehicles, 'workshops' => $workshops])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Registrar</button>
            <a href="{{ route('user.maintenances.index') }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

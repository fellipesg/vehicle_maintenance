@extends('layouts.app')

@section('title', 'Manutenções')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8" data-api-page="maintenances-index">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold">🔧 Manutenções</h1>
        <a href="{{ route('user.maintenances.create') }}" class="btn-primary">+ Nova Manutenção</a>
    </div>

    <div data-maintenances-list>
        <div class="card text-center text-automotive-500">Carregando manutenções...</div>
    </div>
</div>
@endsection

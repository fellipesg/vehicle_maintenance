@extends('layouts.app')

@section('title', 'Oficinas')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <h1 class="mb-6 text-3xl font-bold">🏭 Diretório de Oficinas</h1>

    <form method="GET" class="card mb-6 flex gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nome, cidade ou bairro" class="form-input flex-1">
        <button type="submit" class="btn-primary">Buscar</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($workshops as $workshop)
            <div class="card">
                <h2 class="text-lg font-semibold">🔧 {{ $workshop->name }}</h2>
                <p class="mt-1 text-sm text-automotive-600">{{ $workshop->neighborhood }}, {{ $workshop->city }}/{{ $workshop->state }}</p>
                <p class="mt-2 text-sm">📞 {{ $workshop->phone }}</p>
                @if($workshop->email)<p class="text-sm">✉️ {{ $workshop->email }}</p>@endif
            </div>
        @empty
            <div class="card col-span-full text-center text-automotive-500">Nenhuma oficina encontrada.</div>
        @endforelse
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Oficinas')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8" data-api-page="workshops-index">
    <h1 class="mb-6 text-3xl font-bold">🏭 Diretório de Oficinas</h1>

    <form data-workshops-search class="card mb-6 flex gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nome, cidade ou bairro" class="form-input flex-1">
        <button type="submit" class="btn-primary">Buscar</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-workshops-grid>
        <div class="card col-span-full text-center text-automotive-500">Carregando oficinas...</div>
    </div>
</div>
@endsection

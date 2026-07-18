@extends('layouts.app')

@section('title', 'Editar Oficina')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <span class="badge badge-orange mb-2">🔧 Oficina</span>
    <h1 class="mb-6 text-3xl font-bold">Editar Oficina</h1>

    <form method="POST" action="{{ route('workshop.profile.update') }}" class="card space-y-4">
        @csrf
        @method('PUT')
        @include('workshop.profile._form', ['workshop' => $workshop])
        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Salvar alterações</button>
            <a href="{{ route('workshop.profile.show') }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

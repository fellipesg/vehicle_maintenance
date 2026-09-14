@extends('layouts.app')

@section('title', 'Novo template de garantia')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <span class="badge badge-orange mb-2">🔧 Oficina</span>
    <h1 class="mb-6 text-3xl font-bold">Novo template de garantia</h1>

    <form method="POST" action="{{ route('workshop.warranty-templates.store') }}" class="card space-y-4">
        @csrf
        @include('workshop.warranty-templates._form', ['workshop' => $workshop, 'templatesLocked' => $templatesLocked])
        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Salvar template</button>
            <a href="{{ route('workshop.warranty-templates.index') }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

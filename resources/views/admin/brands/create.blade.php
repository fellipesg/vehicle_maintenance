@extends('layouts.app')

@section('title', 'Nova marca')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.brands.index') }}" class="text-sm text-wrench-600 hover:underline">← Voltar para marcas</a>
        <h1 class="mt-2 text-3xl font-bold">Nova marca</h1>
    </div>

    <form method="POST" action="{{ route('admin.brands.store') }}" class="card space-y-4">
        @csrf
        @include('admin.brands._form')
        <button type="submit" class="btn-primary">Salvar marca</button>
    </form>
</div>
@endsection

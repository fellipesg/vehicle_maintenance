@extends('layouts.app')

@section('title', 'Cadastrar Oficina')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <span class="badge badge-orange mb-2">🔧 Oficina</span>
    <h1 class="mb-6 text-3xl font-bold">Cadastrar Oficina</h1>

    <form method="POST" action="{{ route('workshop.profile.store') }}" class="card space-y-4">
        @csrf
        @include('workshop.profile._form')
        <button type="submit" class="btn-primary">Cadastrar oficina</button>
    </form>
</div>
@endsection

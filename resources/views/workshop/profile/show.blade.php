@extends('layouts.app')

@section('title', 'Minha Oficina')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <span class="badge badge-orange mb-2">🔧 Oficina</span>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold">{{ $workshop?->name ?? 'Minha Oficina' }}</h1>
        @if($workshop)
            <a href="{{ route('workshop.profile.edit') }}" class="btn-secondary">Editar</a>
        @endif
    </div>

    @if($workshop)
        <div class="card space-y-3">
            <p><strong>Telefone:</strong> {{ $workshop->phone }}</p>
            <p><strong>WhatsApp:</strong> {{ $workshop->whatsapp }}</p>
            @if($workshop->email)<p><strong>E-mail:</strong> {{ $workshop->email }}</p>@endif
            <p><strong>Endereço:</strong> {{ $workshop->full_address }}</p>
            @if($workshop->instagram)<p><strong>Instagram:</strong> <a href="{{ $workshop->instagram }}" class="text-wrench-600 hover:underline" target="_blank">{{ $workshop->instagram }}</a></p>@endif
        </div>
    @else
        <div class="card text-center">
            <p class="text-automotive-500">Oficina ainda não cadastrada.</p>
            <a href="{{ route('workshop.profile.create') }}" class="btn-primary mt-4">Cadastrar oficina</a>
        </div>
    @endif
</div>
@endsection

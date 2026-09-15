@extends('layouts.app')

@section('title', 'Código inválido — Revisalog')

@section('content')
<div class="mx-auto max-w-md px-4 py-16 text-center">
    <h1 class="text-2xl font-bold text-automotive-900">Código não encontrado</h1>
    <p class="mt-3 text-automotive-600">Este código de verificação não existe ou não corresponde a um registro verificado.</p>
    <a href="{{ route('home') }}" class="btn-primary mt-8 inline-block">Voltar ao início</a>
</div>
@endsection

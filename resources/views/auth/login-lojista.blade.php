@php
    $config = $portalConfig;
    $submitRoute = route('login.submit', $portal);
@endphp

@extends('layouts.guest')

@section('title', $config['title'])

@section('content')
    <div class="mb-6 text-center">
        @include('auth.partials.portal-icon', ['portal' => 'lojista', 'class' => 'mb-3'])
        <h1 class="text-xl font-semibold">{{ $config['title'] }}</h1>
        <p class="mt-1 text-sm text-automotive-400">{{ $config['subtitle'] }}</p>
    </div>

    @include('auth._login-form', ['submitRoute' => $submitRoute])

    <p class="mt-4 text-center text-sm text-automotive-400">
        <a href="{{ route('login') }}" class="text-wrench-400 hover:underline">← Outros tipos de acesso</a>
    </p>

    @if($config['register'])
        <p class="mt-2 text-center text-sm text-automotive-400">
            Não tem conta? <a href="{{ route('register') }}" class="text-wrench-400 hover:underline">Cadastre sua loja</a>
        </p>
    @endif
@endsection

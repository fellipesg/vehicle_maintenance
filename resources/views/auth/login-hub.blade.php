@extends('layouts.guest')

@section('title', 'Entrar')

@section('content')
    <h1 class="mb-2 text-xl font-semibold">Como você deseja entrar?</h1>
    <p class="mb-6 text-sm text-automotive-400">Escolha o portal correspondente ao seu perfil</p>

    <div class="space-y-3">
        <a href="{{ route('login.usuario') }}"
           class="flex items-center gap-4 rounded-xl border border-automotive-600 bg-automotive-800/50 p-4 transition hover:border-wrench-500 hover:bg-automotive-800">
            <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-automotive-600 text-2xl">👤</span>
            <div>
                <p class="font-semibold">Proprietário de veículo</p>
                <p class="text-sm text-automotive-400">Histórico pessoal de carros e manutenções</p>
            </div>
        </a>

        <a href="{{ route('login.lojista') }}"
           class="flex items-center gap-4 rounded-xl border border-automotive-600 bg-automotive-800/50 p-4 transition hover:border-emerald-500 hover:bg-automotive-800">
            <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-700 text-2xl">🏪</span>
            <div>
                <p class="font-semibold">Lojista / Garagem</p>
                <p class="text-sm text-automotive-400">Estoque de veículos e revisões pré-venda</p>
            </div>
        </a>

        <a href="{{ route('login.admin') }}"
           class="flex items-center gap-4 rounded-xl border border-automotive-600 bg-automotive-800/50 p-4 transition hover:border-orange-500 hover:bg-automotive-800">
            <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-600 text-2xl">⚙️</span>
            <div>
                <p class="font-semibold">Administrador</p>
                <p class="text-sm text-automotive-400">Gestão da plataforma e catálogo</p>
            </div>
        </a>
    </div>

    <p class="mt-6 text-center text-sm text-automotive-400">
        Não tem conta? <a href="{{ route('register') }}" class="text-wrench-400 hover:underline">Cadastre-se</a>
    </p>
@endsection

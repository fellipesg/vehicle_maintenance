@extends('layouts.guest')

@section('title', 'Cadastrar')

@section('content')
    <h1 class="mb-6 text-xl font-semibold">Criar conta</h1>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="form-label !text-automotive-300">Nome</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white">
            @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="form-label !text-automotive-300">E-mail</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white">
            @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="user_type" class="form-label !text-automotive-300">Tipo de conta</label>
            <select name="user_type" id="user_type" required class="form-select !border-automotive-600 !bg-automotive-800 !text-white">
                <option value="user" @selected(old('user_type') === 'user')>👤 Usuário — Proprietário de veículo</option>
                <option value="garage" @selected(old('user_type') === 'garage')>🏪 Garagem — Loja de veículos</option>
                <option value="workshop" @selected(old('user_type') === 'workshop')>🔧 Oficina — Mecânica / serviços</option>
            </select>
            @error('user_type')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="phone" class="form-label !text-automotive-300">Telefone (opcional)</label>
            <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white">
        </div>

        <div>
            <label for="password" class="form-label !text-automotive-300">Senha</label>
            <input type="password" name="password" id="password" required
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white">
            @error('password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="form-label !text-automotive-300">Confirmar senha</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white">
        </div>

        <button type="submit" class="btn-primary w-full">Criar conta</button>
    </form>

    <p class="mt-6 text-center text-sm text-automotive-400">
        Já tem conta? <a href="{{ route('login') }}" class="text-wrench-400 hover:underline">Entrar</a>
    </p>
@endsection

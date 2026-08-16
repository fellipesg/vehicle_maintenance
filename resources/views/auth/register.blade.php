@extends('layouts.guest')

@section('title', 'Cadastrar')

@section('content')
    <h1 class="mb-6 text-xl font-semibold">Criar conta</h1>

    <form method="POST" action="{{ route('register') }}" class="space-y-4" data-password-form>
        @csrf

        <div>
            <label for="name" class="form-label !text-automotive-300">Nome</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white"
                   autocomplete="name">
            @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="form-label !text-automotive-300">E-mail</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white"
                   autocomplete="email">
            @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="document" class="form-label !text-automotive-300">CPF ou CNPJ (opcional)</label>
            <input type="text" name="document" id="document" value="{{ old('document') }}"
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white"
                   data-mask="document" placeholder="000.000.000-00"
                   maxlength="18" autocomplete="off">
            <p class="mt-1 text-sm text-automotive-400" data-field-hint data-default-hint="Armazenado de forma criptografada" data-idle-class="mt-1 text-sm text-automotive-400">Armazenado de forma criptografada</p>
            @error('document')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="phone" class="form-label !text-automotive-300">Telefone (opcional)</label>
            <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white"
                   data-mask="digits" data-max-digits="11" data-min-digits="10"
                   maxlength="11" inputmode="numeric" placeholder="11999999999">
            <p class="mt-1 text-sm text-automotive-400" data-field-hint data-default-hint="Somente números, com DDD" data-idle-class="mt-1 text-sm text-automotive-400">Somente números, com DDD</p>
        </div>

        <div>
            <label for="password" class="form-label !text-automotive-300">Senha</label>
            <input type="password" name="password" id="password" required
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white"
                   data-password-field autocomplete="new-password" minlength="8">
            <ul class="mt-2 space-y-1 text-sm text-automotive-400" data-password-criteria>
                <li data-rule="length" class="flex items-center gap-2">
                    <span data-rule-icon>○</span>
                    <span>Mínimo de 8 caracteres</span>
                </li>
                <li data-rule="match" class="flex items-center gap-2">
                    <span data-rule-icon>○</span>
                    <span>Confirmação igual à senha</span>
                </li>
            </ul>
            <p class="mt-1 text-sm" data-field-hint></p>
            @error('password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="form-label !text-automotive-300">Confirmar senha</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                   class="form-input !border-automotive-600 !bg-automotive-800 !text-white"
                   data-password-confirmation autocomplete="new-password" minlength="8">
            <p class="mt-1 text-sm" data-field-hint></p>
        </div>

        <button type="submit" class="btn-primary w-full">Criar conta</button>
    </form>

    <p class="mt-6 text-center text-sm text-automotive-400">
        Já tem conta? <a href="{{ route('login') }}" class="text-wrench-400 hover:underline">Entrar</a>
    </p>
@endsection

<form method="POST" action="{{ $submitRoute }}" class="space-y-4">
    @csrf

    <div>
        <label for="email" class="form-label !text-automotive-300">E-mail</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
               class="form-input !border-automotive-600 !bg-automotive-800 !text-white">
        @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="password" class="form-label !text-automotive-300">Senha</label>
        <input type="password" name="password" id="password" required
               class="form-input !border-automotive-600 !bg-automotive-800 !text-white">
    </div>

    <label class="flex items-center gap-2 text-sm text-automotive-300">
        <input type="checkbox" name="remember" class="rounded border-automotive-600">
        Lembrar de mim
    </label>

    <button type="submit" class="btn-primary w-full">Entrar</button>
</form>

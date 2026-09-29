@extends('layouts.guest')

@section('title', 'Criar nova senha')

@section('content')
    <x-auth.header icon="lock-closed" title="Criar nova senha" description="Escolha uma senha com pelo menos 8 caracteres. Depois, entre com ela." />

    @if($linkExpired)
        {{-- O token não confere com o e-mail do link (expirou, já foi usado ou foi trocado por um mais novo). --}}
        <div class="space-y-4">
            <x-ui.alert variant="warning" title="Este link expirou ou já foi usado.">
                Os links de redefinição valem por {{ $expiresInMinutes }} minutos e só funcionam uma vez. Peça um novo para criar a senha.
            </x-ui.alert>
            <x-ui.button :href="route('password.request')" size="lg" full icon="envelope">Pedir novo link</x-ui.button>
        </div>
    @else
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4" data-password-form>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            @error('token')
                <x-ui.alert variant="danger" title="Não foi possível redefinir a senha.">
                    {{ $message }}
                    <x-slot:actions>
                        <x-ui.button :href="route('password.request')" variant="secondary" size="sm" icon="envelope">Pedir novo link</x-ui.button>
                    </x-slot:actions>
                </x-ui.alert>
            @enderror

            <x-ui.field name="email" label="E-mail">
                <x-ui.input type="email" :value="$email" required autocomplete="username" inputmode="email" :autofocus="$email === ''" />
            </x-ui.field>

            <div class="space-y-2">
                <x-ui.field name="password" label="Nova senha">
                    <x-ui.input type="password" required minlength="8" autocomplete="new-password" :autofocus="$email !== ''" data-password-field aria-describedby="password-criteria" />
                </x-ui.field>
                @include('auth.partials.password-criteria')
            </div>

            <x-ui.field name="password_confirmation" label="Confirmar nova senha">
                <x-ui.input type="password" required minlength="8" autocomplete="new-password" data-password-confirmation />
            </x-ui.field>

            <x-ui.button type="submit" size="lg" full loading-label="Salvando…">Salvar nova senha</x-ui.button>
        </form>
    @endif

    <div class="mt-6 border-t border-border pt-4 text-sm">
        <x-ui.link :href="route('login')" icon="arrow-left" class="min-h-10">Voltar para a entrada</x-ui.link>
    </div>
@endsection

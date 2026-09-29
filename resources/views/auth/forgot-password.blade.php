@extends('layouts.guest')

@section('title', 'Esqueci minha senha')

@section('content')
    <x-auth.header icon="key" title="Esqueci minha senha" description="Informe o e-mail da sua conta. Enviamos um link para você criar uma senha nova." />

    {{-- A confirmação do envio ("Se este e-mail estiver cadastrado...") vem em session('status'),
         mostrada pelo aviso do layout guest. --}}
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-ui.field name="email" label="E-mail">
            <x-ui.input type="email" required autofocus autocomplete="email" inputmode="email" />
        </x-ui.field>

        <x-ui.button type="submit" size="lg" full icon="envelope" loading-label="Enviando…">Enviar link</x-ui.button>
    </form>

    <p class="mt-4 text-sm text-muted-foreground">
        O link vale por {{ $expiresInMinutes }} minutos. Não chegou? Confira a caixa de spam ou peça de novo daqui a 1 minuto.
    </p>

    <div class="mt-6 border-t border-border pt-4 text-sm">
        <x-ui.link :href="$backUrl" icon="arrow-left" class="min-h-10">Voltar para a entrada</x-ui.link>
    </div>
@endsection

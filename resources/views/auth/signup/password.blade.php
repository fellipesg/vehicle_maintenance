{{--
    Etapa 2 do cadastro em etapas (/cadastro/senha): a senha.

    Variáveis (SignupController::password):
    - $email: o e-mail conferido na etapa 1, mostrado e não editável aqui.
    - $joinsExistingIdentity: true quando o e-mail já tem conta — então a senha pedida é a **dela**,
      porque a senha é da identidade e vale para todos os perfis da pessoa.
    - $profileLabel: o perfil escolhido, para a pessoa confirmar que está no caminho certo.
--}}
@extends('layouts.guest')

@section('title', $joinsExistingIdentity ? 'Confirme sua senha' : 'Crie sua senha')

@section('content')
    <x-auth.header
        icon="key"
        :title="$joinsExistingIdentity ? 'Confirme sua senha' : 'Crie sua senha'"
        :description="$joinsExistingIdentity
            ? 'Este e-mail já tem conta na RevisaLog. Informe a senha dela para criar seu perfil de '.mb_strtolower($profileLabel).'.'
            : 'Esta senha é a sua entrada na RevisaLog.'"
    />

    <x-auth.steps :current="2" class="mt-6" />

    <div class="mt-6 rounded-card border border-border-strong bg-surface-muted/40 p-4">
        <p class="text-xs font-medium text-muted-foreground">Conta</p>
        <p class="mt-0.5 truncate font-medium text-foreground">{{ $email }}</p>
        <p class="mt-0.5 text-sm text-muted-foreground">
            Perfil: {{ $profileLabel }} ·
            <x-ui.link :href="route('signup.start')">Trocar</x-ui.link>
        </p>
    </div>

    @if($joinsExistingIdentity)
        <x-ui.alert variant="info" icon="user-circle" title="Uma senha para todos os seus perfis." class="mt-6">
            Você entra em cada perfil pela área dele, sempre com esta mesma senha. Se não lembra dela,
            redefina primeiro e volte para terminar o cadastro.
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('signup.password.store') }}" class="mt-6 space-y-4" data-password-form>
        @csrf

        <x-ui.field name="password" :label="$joinsExistingIdentity ? 'Senha da sua conta' : 'Senha'" required>
            <x-ui.input
                type="password"
                required
                :minlength="$joinsExistingIdentity ? null : 8"
                :autocomplete="$joinsExistingIdentity ? 'current-password' : 'new-password'"
                :data-password-field="$joinsExistingIdentity ? null : true"
                :aria-describedby="$joinsExistingIdentity ? null : 'password-criteria'"
            />
            @if($joinsExistingIdentity)
                <x-slot:aside>
                    <x-ui.link :href="route('password.request')" class="inline-flex min-h-10 items-center">Esqueci minha senha</x-ui.link>
                </x-slot:aside>
            @endif
        </x-ui.field>

        @unless($joinsExistingIdentity)
            @include('auth.partials.password-criteria')

            <x-ui.field name="password_confirmation" label="Confirmar senha" required>
                <x-ui.input type="password" required minlength="8" autocomplete="new-password" data-password-confirmation />
            </x-ui.field>
        @endunless

        <x-ui.button type="submit" size="lg" full loading-label="Conferindo…">Continuar</x-ui.button>
    </form>
@endsection

{{--
    Cadastro público (só proprietário; oficinas se cadastram em /para-oficinas e lojas falam com a equipe).

    Variáveis (AuthController::showRegister):
    - $emailTaken: o envio anterior parou em "Este e-mail já está cadastrado.". A tela oferece entrar
      ou redefinir a senha, em vez de levar a um segundo cadastro.
    - $intendedNotice: 'search' quando o visitante veio da busca de veículo (depois do cadastro ele
      volta para ela), 'protected' ou null.

    CPF/CNPJ e telefone ficam num bloco recolhido e opcional, aberto quando já têm valor ou erro. Os
    dois aceitam pontuação (o controller guarda só os dígitos), então colar "(11) 99999-9999" não
    corta o número.
--}}
@extends('layouts.guest')

@section('title', 'Crie sua conta de proprietário')

@php
    $optionalFieldsOpen = filled(old('document')) || filled(old('phone')) || $errors->hasAny(['document', 'phone']);
@endphp

@section('content')
    <x-auth.header icon="user" title="Crie sua conta de proprietário" description="Conta gratuita para proprietários de veículos.">
        <p class="text-sm text-muted-foreground">
            Tem uma oficina? <x-ui.link :href="route('workshops.landing')" variant="inline">Cadastre sua oficina</x-ui.link>.
            Lojista? <x-ui.link :href="route('contact.show', ['assunto' => 'partnership'])" variant="inline">Fale com a equipe</x-ui.link>
        </p>
    </x-auth.header>

    @if($intendedNotice === 'search')
        <x-ui.alert variant="info" icon="magnifying-glass" class="mb-6">
            Depois do cadastro, levamos você direto para a busca de veículo.
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-4" data-password-form>
        @csrf

        <x-ui.form-errors />

        <x-ui.field name="name" label="Nome">
            <x-ui.input required autocomplete="name" />
        </x-ui.field>

        <x-ui.field name="email" label="E-mail">
            <x-ui.input type="email" required autocomplete="email" inputmode="email" />
        </x-ui.field>

        @if($emailTaken)
            <x-ui.alert variant="info" icon="user-circle" title="Este e-mail já tem conta na RevisaLog." data-email-taken>
                Entre pela sua área ou, se esqueceu a senha, peça um link para criar uma nova.
                <x-slot:actions>
                    <x-ui.button :href="route('login')" variant="secondary" size="sm" icon="arrow-right-on-rectangle">Entrar</x-ui.button>
                    <x-ui.button :href="route('password.request', ['portal' => 'usuario'])" variant="ghost" size="sm" icon="key">Redefinir senha</x-ui.button>
                </x-slot:actions>
            </x-ui.alert>
        @endif

        <div class="space-y-2">
            <x-ui.field name="password" label="Senha">
                <x-ui.input type="password" required minlength="8" autocomplete="new-password" data-password-field aria-describedby="password-criteria" />
            </x-ui.field>
            @include('auth.partials.password-criteria')
        </div>

        <x-ui.field name="password_confirmation" label="Confirmar senha">
            <x-ui.input type="password" required minlength="8" autocomplete="new-password" data-password-confirmation />
        </x-ui.field>

        <details class="group rounded-card border border-border-strong" data-slot="register-optional-fields" @if($optionalFieldsOpen) open @endif>
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-card px-4 py-2 text-sm font-medium text-foreground transition-colors duration-fast ease-smooth-out hover:bg-surface-muted/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none [&::-webkit-details-marker]:hidden">
                <span>Adicionar CPF e telefone <span class="font-normal text-muted-foreground">(opcional)</span></span>
                <x-ui.icon name="chevron-down" class="size-5 text-muted-foreground transition-transform duration-fast ease-smooth-out group-open:rotate-180 motion-reduce:transition-none" />
            </summary>
            <div class="space-y-4 border-t border-border-strong p-4">
                <x-ui.field name="document" label="CPF ou CNPJ" optional hint="Com ou sem pontuação. Fica guardado de forma criptografada.">
                    <x-ui.input inputmode="numeric" maxlength="18" autocomplete="off" placeholder="000.000.000-00" />
                </x-ui.field>

                <x-ui.field name="phone" label="Telefone" optional hint="Com DDD, ex.: (11) 99999-9999.">
                    <x-ui.input type="tel" inputmode="tel" autocomplete="tel-national" maxlength="15" placeholder="(11) 99999-9999" />
                </x-ui.field>
            </div>
        </details>

        <p class="text-sm text-muted-foreground">
            Ao criar a conta, você aceita os
            <x-ui.link :href="route('legal.terms')" variant="inline" external>Termos de uso</x-ui.link>
            e a
            <x-ui.link :href="route('legal.privacy')" variant="inline" external>Política de privacidade</x-ui.link>.
        </p>

        <x-ui.button type="submit" size="lg" full loading-label="Criando conta…">Criar conta</x-ui.button>
    </form>

    <div class="mt-6 border-t border-border pt-4 text-sm">
        <p class="text-muted-foreground">
            Já tem conta? <x-ui.link :href="route('login')" variant="inline">Entrar</x-ui.link>
        </p>
    </div>
@endsection

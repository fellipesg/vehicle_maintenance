{{--
    Cadastro próprio de oficina (WorkshopSignupController::show).

    Variáveis:
    - $ref: token da prospect do e-mail de convite (repassado num campo oculto), ou null.
    - $defaults: trade_name, cnpj e email vindos da prospect; são só valores iniciais e editáveis.
    - $stateOptions: UF => "Estado (UF)".

    Telefone, CEP e CNPJ usam as máscaras de resources/js/form-ux.js; o CEP (data-cep-autofill) preenche
    rua, bairro, cidade e UF pelo ViaCEP (resources/js/workshop-address-cep.js). O servidor aceita
    pontuação e tira antes de validar. Funciona em 360px: tudo em uma coluna, só Número/Complemento e
    Cidade/UF dividem a linha a partir de sm.
--}}
@extends('layouts.guest')

@section('title', 'Cadastre sua oficina')

@section('meta_description', 'Cadastre sua oficina no RevisaLog. O nome dela fica no histórico do carro com o Selo da oficina. Grátis no lançamento.')

@section('content')
    <x-auth.header icon="wrench-screwdriver" title="Cadastre sua oficina" description="Conta gratuita no lançamento. Funciona assim que o cadastro termina.">
        <p class="text-sm text-muted-foreground">
            Tem uma loja de veículos? <x-ui.link :href="route('contact.show', ['assunto' => 'partnership'])" variant="inline">Fale com a equipe</x-ui.link>
        </p>
    </x-auth.header>

    <form method="POST" action="{{ route('workshops.signup.store') }}" class="mt-6 space-y-6" data-password-form data-workshop-profile-form>
        @csrf
        @if($ref)
            <input type="hidden" name="ref" value="{{ old('ref', $ref) }}">
        @endif

        <x-ui.form-errors />

        @include('auth.partials.workshop-profile-fields')

        <fieldset class="space-y-4">
            <legend class="text-base font-semibold text-foreground">Quem é responsável</legend>

            <x-ui.field name="name" label="Nome da pessoa responsável" required>
                <x-ui.input required autocomplete="name" />
            </x-ui.field>

            <x-ui.field name="email" label="E-mail" hint="É o seu login e o e-mail dos avisos." required>
                <x-ui.input type="email" :value="$defaults['email']" required autocomplete="email" inputmode="email" />
            </x-ui.field>

            <div class="space-y-2">
                <x-ui.field name="password" label="Senha" required>
                    <x-ui.input type="password" required minlength="8" autocomplete="new-password" data-password-field aria-describedby="password-criteria" />
                </x-ui.field>
                @include('auth.partials.password-criteria')
            </div>

            <x-ui.field name="password_confirmation" label="Confirmar senha" required>
                <x-ui.input type="password" required minlength="8" autocomplete="new-password" data-password-confirmation />
            </x-ui.field>
        </fieldset>

        <p class="text-sm text-muted-foreground">
            Ao cadastrar a oficina, você aceita os
            <x-ui.link :href="route('legal.terms')" variant="inline" external>Termos de uso</x-ui.link>
            e a
            <x-ui.link :href="route('legal.privacy')" variant="inline" external>Política de privacidade</x-ui.link>.
        </p>

        <x-ui.button type="submit" size="lg" full loading-label="Cadastrando…">Cadastrar minha oficina</x-ui.button>
    </form>

    <div class="mt-6 border-t border-border pt-4 text-sm">
        <p class="text-muted-foreground">
            Já tem conta? <x-ui.link :href="route('login.oficina')" variant="inline">Entrar como oficina</x-ui.link>
        </p>
    </div>
@endsection

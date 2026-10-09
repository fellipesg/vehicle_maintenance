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

        <fieldset class="space-y-4">
            <legend class="text-base font-semibold text-foreground">A oficina</legend>

            <x-ui.field name="trade_name" label="Nome fantasia" required>
                <x-ui.input :value="$defaults['trade_name']" required maxlength="255" autocomplete="organization" />
            </x-ui.field>

            <x-ui.field name="cnpj" label="CNPJ" hint="Com ou sem pontuação." required>
                <x-ui.input :value="$defaults['cnpj']" required maxlength="18" autocomplete="off" inputmode="numeric" data-mask="cnpj" placeholder="00.000.000/0000-00" />
            </x-ui.field>

            <x-ui.field name="phone" label="WhatsApp ou telefone" hint="Com DDD, só números." required>
                <x-ui.input type="tel" required autocomplete="tel-national" inputmode="numeric" data-mask="digits" data-min-digits="10" data-max-digits="11" placeholder="11999999999" />
            </x-ui.field>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-base font-semibold text-foreground">Endereço</legend>

            <x-ui.field name="cep" label="CEP" hint="8 dígitos. Preenchemos o resto do endereço para você." required>
                <x-ui.input required autocomplete="postal-code" inputmode="numeric" data-mask="digits" data-min-digits="8" data-max-digits="8" data-cep-autofill placeholder="01310100" class="tabular-nums" />
            </x-ui.field>

            <x-ui.field name="street" label="Rua" required>
                <x-ui.input required maxlength="255" autocomplete="address-line1" />
            </x-ui.field>

            <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
                <x-ui.field name="number" label="Número" required>
                    <x-ui.input required maxlength="20" autocomplete="off" />
                </x-ui.field>
                <x-ui.field name="complement" label="Complemento" optional>
                    <x-ui.input maxlength="255" autocomplete="address-line2" />
                </x-ui.field>
            </div>

            <x-ui.field name="neighborhood" label="Bairro" required>
                <x-ui.input required maxlength="255" autocomplete="address-level3" />
            </x-ui.field>

            <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
                <x-ui.field name="city" label="Cidade" required>
                    <x-ui.input required maxlength="255" autocomplete="address-level2" />
                </x-ui.field>
                <x-ui.field name="state" label="UF" required>
                    <x-ui.select :options="$stateOptions" placeholder="Selecione a UF" required autocomplete="address-level1" />
                </x-ui.field>
            </div>
        </fieldset>

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

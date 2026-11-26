{{--
    Etapa 3 do cadastro em etapas (/cadastro/dados): os dados do perfil escolhido.

    Variáveis (SignupController::details):
    - $profile: App\Enums\Portal::Owner ou ::Workshop — decide quais campos aparecem.
    - $email: o e-mail já conferido, mostrado e não editável.
    - $stateOptions: UFs, para o endereço da oficina.

    Os campos da oficina vêm de auth.partials.workshop-profile-fields, os mesmos do cadastro da
    landing.
--}}
@extends('layouts.guest')

@section('title', $profile === \App\Enums\Portal::Workshop ? 'Dados da oficina' : 'Seus dados')

@section('content')
    <x-auth.header
        :icon="$profile === \App\Enums\Portal::Workshop ? 'wrench-screwdriver' : 'user'"
        :title="$profile === \App\Enums\Portal::Workshop ? 'Dados da oficina' : 'Seus dados'"
        :description="$profile === \App\Enums\Portal::Workshop
            ? 'Último passo. É o que aparece no Selo da oficina e no diretório.'
            : 'Último passo para criar sua conta.'"
    />

    <x-auth.steps :current="3" class="mt-6" />

    <div class="mt-6 rounded-card border border-border-strong bg-surface-muted/40 p-4">
        <p class="text-xs font-medium text-muted-foreground">Conta</p>
        <p class="mt-0.5 truncate font-medium text-foreground">{{ $email }}</p>
        <p class="mt-0.5 text-sm text-muted-foreground">Perfil: {{ $profile->label() }}</p>
    </div>

    <form method="POST" action="{{ route('signup.details.store') }}" class="mt-6 space-y-6">
        @csrf

        <x-ui.form-errors />

        @if($profile === \App\Enums\Portal::Workshop)
            @include('auth.partials.workshop-profile-fields')

            <fieldset class="space-y-4">
                <legend class="text-base font-semibold text-foreground">Quem é responsável</legend>

                <x-ui.field name="name" label="Nome da pessoa responsável" required>
                    <x-ui.input required autocomplete="name" />
                </x-ui.field>
            </fieldset>
        @else
            <fieldset class="space-y-4">
                <legend class="text-base font-semibold text-foreground">Seus dados</legend>

                <x-ui.field name="name" label="Nome" required>
                    <x-ui.input required autocomplete="name" />
                </x-ui.field>

                <x-ui.field name="phone" label="Telefone" hint="Com DDD, só números." optional>
                    <x-ui.input type="tel" autocomplete="tel-national" inputmode="numeric" data-mask="digits" data-min-digits="10" data-max-digits="11" placeholder="11999999999" />
                </x-ui.field>

                <x-ui.field name="document" label="CPF ou CNPJ" hint="Só números. Usamos para confirmar a posse do veículo." optional>
                    <x-ui.input autocomplete="off" inputmode="numeric" data-mask="digits" data-min-digits="11" data-max-digits="14" />
                </x-ui.field>
            </fieldset>
        @endif

        <p class="text-sm text-muted-foreground">
            Ao criar a conta, você aceita os
            <x-ui.link :href="route('legal.terms')" variant="inline" external>Termos de uso</x-ui.link>
            e a
            <x-ui.link :href="route('legal.privacy')" variant="inline" external>Política de privacidade</x-ui.link>.
        </p>

        <x-ui.button type="submit" size="lg" full loading-label="Criando…">
            {{ $profile === \App\Enums\Portal::Workshop ? 'Cadastrar minha oficina' : 'Criar minha conta' }}
        </x-ui.button>
    </form>
@endsection

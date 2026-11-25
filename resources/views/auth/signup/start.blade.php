{{--
    Etapa 1 do cadastro em etapas (/cadastro): o perfil e o e-mail.

    Variáveis (SignupController::start):
    - $profiles: [slug, label, description, icon, url] dos perfis. slug null + url preenchida é o
      perfil que não se cadastra por aqui (lojista, que entra por Google ou Apple).
    - $flow: App\Support\Account\SignupFlow, para repreencher o e-mail de quem voltou.

    O e-mail é conferido no envio: se já houver conta do perfil escolhido, o erro cai no campo de
    e-mail; se houver conta de outro perfil, a etapa seguinte pede a senha dela.
--}}
@extends('layouts.guest')

@section('title', 'Criar conta')

@section('content')
    <x-auth.header
        icon="user-circle"
        title="Criar conta"
        description="Escolha o tipo de conta e informe seu e-mail."
    />

    <x-auth.steps :current="1" class="mt-6" />

    <form method="POST" action="{{ route('signup.profile') }}" class="mt-6 space-y-6">
        @csrf

        <x-ui.fieldset name="profile" legend="Tipo de conta" required>
            <x-ui.radio-cards :columns="1" :options="collect($profiles)
                ->filter(fn (array $profile): bool => $profile['slug'] !== null)
                ->mapWithKeys(fn (array $profile): array => [$profile['slug'] => [
                    'label' => $profile['label'],
                    'description' => $profile['description'],
                    'icon' => $profile['icon'],
                ]])
                ->all()" :selected="old('profile', $flow->profile()?->value === 'workshop' ? 'oficina' : null)" />
        </x-ui.fieldset>

        <x-ui.field name="email" label="E-mail" hint="É o seu login. Se você já tem conta na RevisaLog, use o mesmo e-mail." required>
            <x-ui.input type="email" :value="old('email', $flow->email())" required autocomplete="email" inputmode="email" />
        </x-ui.field>

        <x-ui.button type="submit" size="lg" full loading-label="Conferindo…">Continuar</x-ui.button>
    </form>

    @foreach($profiles as $profile)
        @if($profile['slug'] === null)
            <div class="mt-6 border-t border-border pt-6">
                <x-ui.alert variant="info" :icon="$profile['icon']" :title="$profile['label']">
                    {{ $profile['description'] }}
                    <x-ui.link :href="$profile['url']" arrow class="mt-2 inline-flex">Ir para a Área do Lojista</x-ui.link>
                </x-ui.alert>
            </div>
        @endif
    @endforeach

    <p class="mt-6 text-sm text-muted-foreground">
        Já tem conta? <x-ui.link :href="route('login')">Entrar</x-ui.link>
    </p>
@endsection

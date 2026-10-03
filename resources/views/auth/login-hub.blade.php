{{--
    Hub de entrada (/login): os 3 perfis públicos. O Painel Administrador abre só por /login/admin.

    Variáveis (AuthController::showLoginHub):
    - $portalOptions: [slug, label, description, icon, url] de Proprietário, Lojista e Oficina.
    - $intendedNotice: 'search' (o visitante tentou abrir a busca de veículo), 'protected' (outra
      página que exige conta) ou null.
--}}
@extends('layouts.guest')

@section('title', 'Entrar')

@section('content')
    <x-auth.header title="Entrar" description="Escolha o tipo da sua conta." />

    @if($intendedNotice === 'search')
        <x-ui.alert variant="info" icon="magnifying-glass" title="A busca de veículo exige uma conta." class="mb-6">
            Para consultar o histórico de um veículo, entre ou crie sua conta grátis de proprietário. Depois, levamos você direto para a busca.
        </x-ui.alert>
    @elseif($intendedNotice === 'protected')
        <x-ui.alert variant="info" icon="lock-closed" class="mb-6">
            Entre para continuar. Essa página exige uma conta.
        </x-ui.alert>
    @endif

    <ul role="list" class="space-y-3" aria-label="Tipos de conta">
        @foreach($portalOptions as $portalOption)
            <x-auth.portal-option
                :href="$portalOption['url']"
                :icon="$portalOption['icon']"
                :title="$portalOption['label']"
                :description="$portalOption['description']"
                :highlighted="$intendedNotice === 'search' && $portalOption['slug'] === 'usuario'"
            />
        @endforeach
    </ul>

    <div class="mt-6 space-y-3 border-t border-border pt-6">
        @if($intendedNotice === 'search')
            <x-ui.button :href="route('register')" size="lg" full>Criar conta grátis</x-ui.button>
        @else
            <p class="text-sm text-muted-foreground">
                Proprietário sem conta? <x-ui.link :href="route('register')" variant="inline">Criar conta grátis</x-ui.link>
            </p>
        @endif
        <p class="text-sm text-muted-foreground">
            Lojista ou oficina? <x-ui.link :href="route('contact.show', ['assunto' => 'partnership'])" variant="inline">Fale com a equipe</x-ui.link>
        </p>
    </div>
@endsection

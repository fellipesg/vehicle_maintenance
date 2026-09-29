{{--
    Tela de login dos 4 portais (/login/usuario, /login/lojista, /login/oficina e /login/admin).

    Variáveis (AuthController::showLogin):
    - $portal: slug da URL (usuario, lojista, oficina ou admin), que vai para o POST e para o
      "Esqueci minha senha".
    - $portalConfig: title (o H1 e o <title>), subtitle, icon e footer (prompt, label e url do link
      de rodapé, ou null no admin).
--}}
@extends('layouts.guest')

@section('title', $portalConfig['title'])

@section('content')
    <x-auth.header :icon="$portalConfig['icon']" :title="$portalConfig['title']" :description="$portalConfig['subtitle']" />

    @include('auth._login-form', ['submitRoute' => route('login.submit', $portal), 'portal' => $portal])

    <div class="mt-6 space-y-2 border-t border-border pt-4 text-sm">
        <p>
            <x-ui.link :href="route('login')" icon="arrow-left" class="min-h-10">Trocar tipo de acesso</x-ui.link>
        </p>
        @if($portalConfig['footer'] !== null)
            <p class="text-muted-foreground">
                {{ $portalConfig['footer']['prompt'] }} <x-ui.link :href="$portalConfig['footer']['url']" variant="inline">{{ $portalConfig['footer']['label'] }}</x-ui.link>
            </p>
        @endif
    </div>
@endsection

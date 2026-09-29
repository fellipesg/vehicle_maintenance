{{--
    Menu mobile (abaixo de lg): <x-ui.sheet> escuro pela esquerda, aberto pelo "Abrir menu" da navbar
    (HSOverlay do Preline: Esc, clique no fundo, foco preso e devolvido ao botão). Tem os mesmos
    destinos da topbar, então nada some numa largura sem ir para cá.

    - Logado: conta (avatar, nome e chip do perfil), ação principal do portal, destinos do portal e,
      no bloco "Conta", Buscar veículo, Notificações, Minha conta, Trocar de área (admin), Ajuda e
      contato e Termos e privacidade. "Sair" fica no rodapé do painel.
    - Visitante: âncoras da landing (só na home) e Blog; "Começar grátis" e "Entrar" no rodapé.

    data-shell-overlay liga o painel ao layouts.partials.shell-script: âncora da própria página
    (ex.: #preco) fecha o menu, e a partir de lg um menu aberto fecha sozinho.

    Variáveis vindas da navbar: $shellPortal, $portalNavItems, $portalPrimaryAction,
    $publicNavItems e $unreadNotificationsCount.
--}}
@php
    $mobileNavUser = auth()->user();
    $mobileNavAccountItems = [];

    if ($mobileNavUser !== null) {
        $mobileNavAreaSwitches = collect(\App\Enums\Portal::accessibleBy($mobileNavUser))
            ->reject(fn (\App\Enums\Portal $portal): bool => $portal === $shellPortal)
            ->filter(fn (\App\Enums\Portal $portal): bool => \Illuminate\Support\Facades\Route::has($portal->dashboardRoute()))
            ->map(fn (\App\Enums\Portal $portal): array => [
                'label' => $portal->switchLabel(),
                'href' => route($portal->dashboardRoute()),
                'icon' => $portal === \App\Enums\Portal::Admin ? 'cog-6-tooth' : 'home',
                'attributes' => ['data-area-switch' => $portal->value],
            ])
            ->values()
            ->all();

        $mobileNavAccountItems = array_values(array_filter([
            [
                'label' => 'Buscar veículo',
                'href' => route('vehicle.search'),
                'active' => request()->routeIs('vehicle.search'),
                'icon' => 'magnifying-glass',
            ],
            [
                'label' => 'Notificações',
                'href' => route('notifications.index'),
                'active' => request()->routeIs('notifications.*'),
                'icon' => 'bell',
                'badge' => $unreadNotificationsCount > 0 ? ($unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount) : null,
                'badgeLabel' => $unreadNotificationsCount === 1 ? 'não lida' : 'não lidas',
            ],
            \Illuminate\Support\Facades\Route::has('account.edit') ? [
                'label' => 'Minha conta',
                'href' => route('account.edit'),
                'active' => request()->routeIs('account.*'),
                'icon' => 'user-circle',
            ] : null,
            ...$mobileNavAreaSwitches,
            [
                'label' => 'Ajuda e contato',
                'href' => route('contact.show'),
                'active' => request()->routeIs('contact.*'),
                'icon' => 'question-mark-circle',
            ],
            [
                'label' => 'Termos e privacidade',
                'href' => route('legal.terms'),
                'active' => request()->routeIs('legal.*'),
                'icon' => 'document-text',
            ],
        ]));
    }
@endphp

<x-ui.sheet
    id="menu-mobile"
    title="Menu"
    side="left"
    size="sm"
    inverse
    class="lg:hidden"
    data-shell-overlay
    data-shell-overlay-close-from="lg"
>
    @if($mobileNavUser !== null)
        <div class="flex items-center gap-3" data-mobile-nav-account>
            <x-ui.avatar :name="$mobileNavUser->name" :src="$mobileNavUser->avatar_url" />
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-foreground">{{ $mobileNavUser->name }}</p>
                <x-ui.badge variant="primary" size="sm" class="mt-1">{{ $shellPortal->label() }}</x-ui.badge>
            </div>
        </div>

        @if($portalPrimaryAction)
            <x-ui.button :href="$portalPrimaryAction['href']" :icon="$portalPrimaryAction['icon']" full class="mt-4" data-shell-primary-action>{{ $portalPrimaryAction['label'] }}</x-ui.button>
        @endif

        @if($portalNavItems !== [])
            <x-ui.nav label="Menu do portal" variant="vertical" :items="$portalNavItems" class="mt-4" />
        @endif

        <nav aria-labelledby="menu-mobile-conta" class="mt-4 border-t border-border pt-4">
            <p id="menu-mobile-conta" class="mb-1 px-3 text-xs font-semibold tracking-wider text-subtle-foreground uppercase">Conta</p>
            <x-ui.nav variant="vertical" :items="$mobileNavAccountItems" />
        </nav>

        <x-slot:footer>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" icon="arrow-right-start-on-rectangle" full>Sair</x-ui.button>
            </form>
        </x-slot:footer>
    @else
        <x-ui.nav label="Menu do site" variant="vertical" :items="$publicNavItems" />

        <x-slot:footer class="flex flex-col gap-2">
            <x-ui.button :href="route('register')" full>Começar grátis</x-ui.button>
            <x-ui.button variant="secondary" :href="route('login')" full>Entrar</x-ui.button>
        </x-slot:footer>
    @endif
</x-ui.sheet>

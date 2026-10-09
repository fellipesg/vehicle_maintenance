{{--
    Topbar de layouts.app, com a mesma anatomia nos três portais e no site público. Recebe do layout
    $shellPortal (App\Enums\Portal, ou null para visitante); itens, rótulo, Início e ação principal
    vêm do enum, não de match nesta view.

    Logado, a partir de lg: logo (Início da área) · destinos do portal (aria-current no ativo) · ação
    principal · busca de veículo · sino · menu de conta (avatar). Abaixo de lg: logo · busca (ícone) ·
    sino · "Abrir menu", que abre layouts.partials.mobile-nav com os mesmos destinos e a conta.
    Visitante: âncoras da landing e Blog a partir de lg, Entrar e "Começar grátis". Na home as âncoras
    são #seção (rolagem na própria página); nas demais páginas apontam para a home, como
    home#seção.

    Paleta de comandos (logado): a busca de veículo é também o gatilho da <x-ui.command> (Ctrl K,
    ⌘K no Mac), com os destinos do portal, a ação principal e "Buscar veículo por placa, chassi ou
    RENAVAM", que abre a busca com o texto. Sem JS, a busca continua sendo o link da página de busca.
--}}
@php
    $navbarUser = auth()->user();
    $unreadNotificationsCount = $navbarUser ? $navbarUser->unreadNotifications()->count() : 0;
    $portalNavItems = $shellPortal?->navigationItems() ?? [];
    // A topbar mostra só itens soltos e sem ícone; grupos (sidebar do admin) ficam no menu mobile.
    $topbarNavItems = collect($portalNavItems)->contains(fn (array $entry): bool => isset($entry['items']))
        ? []
        : array_map(fn (array $item): array => array_merge($item, ['icon' => null]), $portalNavItems);
    $portalPrimaryAction = $shellPortal?->primaryAction();
    $portalPrimaryAction = $portalPrimaryAction !== null && \Illuminate\Support\Facades\Route::has($portalPrimaryAction['route'])
        ? $portalPrimaryAction + ['href' => route($portalPrimaryAction['route'])]
        : null;
    // Âncoras da landing, na ordem das seções, em todas as páginas públicas. Na home o href é só
    // #seção; nas demais páginas aponta para a home#seção. "Produto" sai da topbar entre
    // lg e xl (1024–1279px), onde as seis âncoras mais Entrar e "Começar grátis" não cabem com
    // folga; continua no menu mobile e a partir de xl.
    $landingAnchorPrefix = request()->routeIs('home') ? '' : route('home');
    $publicNavItems = [
        ['label' => 'Como funciona', 'href' => $landingAnchorPrefix.'#como-funciona'],
        ['label' => 'Procedência', 'href' => $landingAnchorPrefix.'#procedencia'],
        ['label' => 'Produto', 'href' => $landingAnchorPrefix.'#produto', 'itemClass' => 'lg:max-xl:hidden'],
        ['label' => 'Para quem', 'href' => $landingAnchorPrefix.'#para-quem'],
        ['label' => 'Preço', 'href' => $landingAnchorPrefix.'#preco'],
        ['label' => 'Blog', 'href' => route('blog.index'), 'active' => request()->routeIs('blog.*')],
    ];
    $isVehicleSearchPage = request()->routeIs('vehicle.search');
@endphp

<header class="theme-inverse sticky top-0 z-50 border-b border-border bg-background text-foreground shadow-lg" data-shell-topbar>
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:gap-6">
        <a
            href="{{ $shellPortal ? route($shellPortal->dashboardRoute()) : route('home') }}"
            class="flex shrink-0 items-center rounded-control"
            data-shell-logo
        >
            <img
                src="{{ \App\Support\AppStorage::brandUrl('lockup-horizontal.png') }}"
                alt="RevisaLog"
                class="h-8 w-auto sm:h-9"
            >
            <span class="sr-only">{{ $shellPortal ? ', Início' : ', página inicial' }}</span>
        </a>

        @if($shellPortal)
            @if($topbarNavItems !== [])
                <x-ui.nav label="Principal" :items="$topbarNavItems" class="hidden min-w-0 lg:block *:flex-nowrap" />
            @endif

            <div class="ms-auto flex shrink-0 items-center gap-1 sm:gap-2">
                @if($portalPrimaryAction)
                    <div class="hidden lg:block">
                        <x-ui.button :href="$portalPrimaryAction['href']" :icon="$portalPrimaryAction['icon']" data-shell-primary-action>{{ $portalPrimaryAction['label'] }}</x-ui.button>
                    </div>
                @endif

                {{--
                    Busca de veículo: só a lupa abaixo de xl (com nome acessível), campo de busca com o
                    atalho a partir de xl. Com JS, o clique abre a paleta de comandos.
                --}}
                <div class="relative flex shrink-0">
                    <a
                        href="{{ route('vehicle.search') }}"
                        @if($isVehicleSearchPage) aria-current="page" @endif
                        data-shell-search
                        data-command-open="comandos"
                        class="inline-flex size-10 shrink-0 items-center justify-center gap-2 rounded-control text-muted-foreground transition-colors duration-fast hover:bg-surface-muted hover:text-foreground aria-[current=page]:bg-surface-muted aria-[current=page]:text-foreground motion-reduce:transition-none xl:w-52 xl:justify-start xl:border xl:border-border-strong xl:bg-surface xl:pr-16 xl:pl-3 xl:text-sm"
                    >
                        <x-ui.icon name="magnifying-glass" class="size-5" />
                        <span class="sr-only xl:not-sr-only">Buscar veículo</span>
                    </a>
                    <kbd class="pointer-events-none absolute top-1/2 right-2 hidden h-6 -translate-y-1/2 items-center rounded border border-border bg-surface-muted px-1.5 font-sans text-xs font-medium text-muted-foreground xl:inline-flex" aria-hidden="true" data-command-shortcut>Ctrl K</kbd>
                </div>

                <x-notification-bell :unread-count="$unreadNotificationsCount" />

                <div class="hidden lg:flex">
                    @include('layouts.partials.account-menu')
                </div>

                <div class="flex lg:hidden">
                    <x-ui.icon-button
                        icon="bars-3"
                        label="Abrir menu"
                        size="lg"
                        :expanded="false"
                        aria-controls="menu-mobile"
                        aria-haspopup="dialog"
                        data-hs-overlay="#menu-mobile"
                    />
                </div>
            </div>
        @else
            <x-ui.nav label="Principal" :items="$publicNavItems" class="hidden min-w-0 lg:block *:flex-nowrap" />

            <div class="ms-auto flex shrink-0 items-center gap-2">
                <x-ui.button variant="ghost" :href="route('login')">Entrar</x-ui.button>
                <x-ui.button :href="route('register')" class="max-sm:hidden">Começar grátis</x-ui.button>

                <div class="flex lg:hidden">
                    <x-ui.icon-button
                        icon="bars-3"
                        label="Abrir menu"
                        size="lg"
                        :expanded="false"
                        aria-controls="menu-mobile"
                        aria-haspopup="dialog"
                        data-hs-overlay="#menu-mobile"
                    />
                </div>
            </div>
        @endif
    </div>
</header>

@if($shellPortal)
    <x-ui.command
        :destinations="$portalNavItems"
        :actions="$portalPrimaryAction ? [$portalPrimaryAction] : []"
        :search-action="route('vehicle.search')"
        search-param="identifier"
    />
@endif

@include('layouts.partials.mobile-nav')

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @php
        /*
         * Shell do admin: sidebar por domínio (sticky a partir de md, gaveta HSOverlay abaixo disso),
         * topbar com trilha e busca de veículo, e o conteúdo rolando com o documento.
         *
         * A partir de md a sidebar recolhe para só ícones (<x-ui.sidebar-toggle>, na topbar): o estado
         * fica em data-sidebar="collapsed" no <html>, aplicado pelo script do <head> antes da primeira
         * pintura, e as classes md:in-data-[sidebar=collapsed]:* daqui e do nav-admin obedecem a ele.
         * A paleta de comandos (<x-ui.command>, Ctrl K / ⌘K e o botão "Ir para…") lista os destinos do
         * Portal::Admin, as ações de criar e a busca de veículo, que envia para a lista de veículos.
         *
         * Cada view admin passa só o nome da página em @section('title') (o <title> vira
         * "{Página} · Admin · RevisaLog"), desenha o único <h1> com <x-ui.page-header> e define a
         * trilha da topbar em $adminBreadcrumbs, no formato de <x-ui.breadcrumb>:
         *     @php($adminBreadcrumbs = [['Catálogo'], ['Marcas e modelos', route('admin.brands.index')], [$brand->name]])
         * Sem $adminBreadcrumbs, a trilha mostra só o nome da página.
         *
         * Seções opcionais:
         * - admin_content_width: largura máxima do conteúdo (padrão max-w-7xl; formulários usam max-w-3xl).
         * - admin_main_class e admin_content_wrapper_class: para telas de altura cheia, como os mapas.
         * As views não criam outro contêiner com mx-auto/px/py: o espaçamento é do layout.
         */
        $adminPageName = $__env->hasSection('page_heading')
            ? trim($__env->yieldContent('page_heading'))
            : \App\Support\DocumentTitle::pageName($__env->yieldContent('title'));
        $adminTrail = $adminBreadcrumbs ?? [[html_entity_decode($adminPageName, ENT_QUOTES | ENT_HTML5, 'UTF-8')]];
        $adminContentWidth = trim($__env->yieldContent('admin_content_width', 'max-w-7xl'));
        $adminUser = auth()->user();
        // A conta admin também tem o portal do user_type dela ("Minha área de proprietário").
        $adminOwnPortal = $adminUser?->portal();
        $adminShowsTopbarSearch = ! request()->routeIs('admin.vehicles.index');
        // Ações da paleta de comandos: criar conteúdo e voltar à própria área da conta.
        $adminCommandActions = collect([
            ['label' => 'Novo artigo', 'route' => 'admin.blog.create', 'icon' => 'plus'],
            ['label' => 'Nova marca', 'route' => 'admin.brands.create', 'icon' => 'plus'],
            $adminOwnPortal ? ['label' => $adminOwnPortal->switchLabel(), 'route' => $adminOwnPortal->dashboardRoute(), 'icon' => 'home'] : null,
        ])
            ->filter(fn (?array $action): bool => $action !== null && \Illuminate\Support\Facades\Route::has($action['route']))
            ->map(fn (array $action): array => $action + ['href' => route($action['route'])])
            ->values()
            ->all();
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{!! \App\Support\DocumentTitle::compose($__env->yieldContent('title') ?: 'Admin', 'Admin') !!}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <x-brand-head-icons />
    <script>
        // Sidebar recolhida (escolha salva por resources/js/ui/sidebar.js): aplicada antes da primeira
        // pintura, para a sidebar não abrir larga e encolher. Sem armazenamento, fica expandida.
        try {
            if (window.localStorage.getItem('revisalog:admin-sidebar') === 'collapsed') {
                document.documentElement.dataset.sidebar = 'collapsed';
            }
        } catch (error) {
            // Modo privado ou armazenamento bloqueado: segue expandida.
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-dvh bg-background font-sans text-foreground antialiased">
    @include('layouts.partials.skip-link')

    <div class="min-h-dvh md:flex">
        {{--
            Uma só sidebar para as duas larguras. A partir de md ela fica no fluxo, sticky e com a
            altura da tela. Abaixo de md é a gaveta do HSOverlay (Preline): fechada fica com
            display:none, fora da ordem de tabulação; aberta tem fundo, Esc, foco preso e devolvido ao
            botão "Abrir menu", e o script do fim da página a anuncia como diálogo modal.
            data-close-from / data-shell-overlay-close-from fecham a gaveta se a tela crescer até md.
        --}}
        <aside
            id="nav-admin"
            aria-label="Menu da administração"
            tabindex="-1"
            data-shell-overlay
            data-shell-overlay-close-from="md"
            data-ui-sheet
            data-close-from="md"
            class="hs-overlay theme-inverse fixed inset-y-0 start-0 z-[60] hidden h-dvh w-[min(18rem,calc(100vw-3rem))] -translate-x-full bg-sidebar text-sidebar-foreground shadow-xl transition-transform duration-slow ease-smooth-out hs-overlay-open:translate-x-0 motion-reduce:transition-none md:sticky md:top-0 md:z-40 md:flex md:w-60 md:shrink-0 md:translate-x-0 md:border-e md:border-sidebar-border md:shadow-none md:transition-[width] md:duration-base md:ease-linear md:in-data-[sidebar=collapsed]:w-16"
            data-hs-overlay-options='{"backdropClasses":"hs-overlay-backdrop fixed inset-0 bg-overlay transition-opacity duration-slow ease-smooth-out motion-reduce:transition-none"}'
        >
            <div class="flex h-full w-full flex-col">
                <div class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border px-4">
                    <a href="{{ route('admin.dashboard') }}" class="flex min-h-10 items-center gap-2.5 rounded-control">
                        <img src="{{ \App\Support\AppStorage::brandUrl('app-icon.png') }}" alt="" class="size-8 shrink-0">
                        <span class="leading-tight md:in-data-[sidebar=collapsed]:sr-only">
                            <span class="block font-semibold tracking-tight text-foreground">RevisaLog</span>
                            <span class="block text-xs text-sidebar-muted">Administração</span>
                        </span>
                    </a>
                    <x-ui.icon-button
                        icon="x-mark"
                        label="Fechar menu"
                        data-hs-overlay="#nav-admin"
                        class="-mr-2 md:hidden"
                    />
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
                    @include('layouts.partials.nav-admin')
                </div>

                @if($adminUser)
                    <div class="shrink-0 border-t border-sidebar-border p-3" data-admin-account>
                        <x-ui.dropdown id="conta-admin" :label="'Conta de '.$adminUser->name" placement="top-start" width="lg" class="w-full">
                            <x-slot:trigger class="flex min-h-12 w-full items-center gap-3 rounded-control px-2 py-1.5 text-left text-sm text-sidebar-foreground transition-colors duration-fast hover:bg-surface-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none md:in-data-[sidebar=collapsed]:justify-center md:in-data-[sidebar=collapsed]:px-0">
                                <x-ui.avatar :name="$adminUser->name" size="sm" />
                                <span class="min-w-0 flex-1 md:in-data-[sidebar=collapsed]:sr-only">
                                    <span class="block truncate font-medium text-foreground">{{ $adminUser->name }}</span>
                                    <span class="block text-xs text-sidebar-muted">Administrador</span>
                                </span>
                                <x-ui.icon name="ellipsis-horizontal" class="size-5 shrink-0 md:in-data-[sidebar=collapsed]:hidden" />
                            </x-slot:trigger>

                            <x-ui.dropdown-item heading>
                                <span class="block truncate">{{ $adminUser->email }}</span>
                            </x-ui.dropdown-item>
                            @if($adminOwnPortal && \Illuminate\Support\Facades\Route::has($adminOwnPortal->dashboardRoute()))
                                <x-ui.dropdown-item :href="route($adminOwnPortal->dashboardRoute())" icon="home">{{ $adminOwnPortal->switchLabel() }}</x-ui.dropdown-item>
                            @endif
                            <x-ui.dropdown-item :href="route('home')" icon="globe-alt">Ver site</x-ui.dropdown-item>
                            <x-ui.dropdown-item :href="route('vehicle.search')" icon="magnifying-glass">Buscar veículo</x-ui.dropdown-item>
                            @if(\Illuminate\Support\Facades\Route::has('account.edit'))
                                <x-ui.dropdown-item :href="route('account.edit')" icon="user-circle">Minha conta</x-ui.dropdown-item>
                            @endif
                            <x-ui.dropdown-item separator />
                            <x-ui.dropdown-item :action="route('logout')" icon="arrow-right-start-on-rectangle">Sair</x-ui.dropdown-item>
                        </x-ui.dropdown>
                    </div>
                @endif
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-surface px-4 md:px-6" data-admin-topbar>
                <x-ui.icon-button
                    icon="bars-3"
                    label="Abrir menu"
                    :expanded="false"
                    aria-controls="nav-admin"
                    aria-haspopup="dialog"
                    data-hs-overlay="#nav-admin"
                    class="-ml-2 md:hidden"
                />
                <x-ui.sidebar-toggle target="nav-admin" class="-ml-2" />

                <div class="min-w-0 flex-1">
                    <x-ui.breadcrumb :items="$adminTrail" />
                </div>

                @if($adminShowsTopbarSearch)
                    <form method="GET" action="{{ route('admin.vehicles.index') }}" role="search" aria-label="Buscar veículo" class="hidden shrink-0 sm:block">
                        <label for="admin-topbar-search" class="sr-only">Buscar veículo por placa, chassi ou RENAVAM</label>
                        <x-ui.input
                            type="search"
                            name="search"
                            id="admin-topbar-search"
                            leading-icon="magnifying-glass"
                            placeholder="Placa, chassi ou RENAVAM"
                            autocomplete="off"
                            class="w-56 lg:w-72"
                        />
                    </form>
                    <x-ui.icon-button
                        icon="magnifying-glass"
                        label="Buscar veículo"
                        :href="route('admin.vehicles.index')"
                        data-command-open="comandos"
                        class="-mr-2 sm:hidden"
                    />
                @endif

                <button
                    type="button"
                    data-command-open="comandos"
                    aria-keyshortcuts="Control+K"
                    class="hidden h-10 shrink-0 items-center gap-2 rounded-control border border-border-strong bg-surface pr-1.5 pl-3 text-sm font-medium text-muted-foreground shadow-xs transition-colors duration-fast ease-smooth-out hover:bg-surface-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none lg:inline-flex"
                    data-admin-command-trigger
                >
                    Ir para…
                    <kbd class="inline-flex h-6 items-center rounded border border-border bg-surface-muted px-1.5 font-sans text-xs font-medium text-muted-foreground" aria-hidden="true" data-command-shortcut>Ctrl K</kbd>
                </button>
            </header>

            <main id="conteudo" tabindex="-1" class="@yield('admin_main_class', 'flex-1')">
                @include('layouts.partials.flash', ['flashWrapperClass' => $__env->yieldContent('admin_flash_wrapper_class', 'mx-auto w-full '.$adminContentWidth.' px-4 pt-4 md:px-6')])

                <div class="@yield('admin_content_wrapper_class', 'mx-auto w-full '.$adminContentWidth.' px-4 py-6 md:px-6')">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <x-ui.confirm-dialog />
    <x-ui.toaster />
    <x-ui.command
        :destinations="\App\Enums\Portal::Admin->navigationItems()"
        :actions="$adminCommandActions"
        :search-action="route('admin.vehicles.index')"
        search-param="search"
    />

    @include('layouts.partials.shell-script')
    <script>
        // Abaixo de md a sidebar abre como gaveta modal: enquanto está aberta, é anunciada como
        // diálogo. No desktop ela nunca abre por overlay e continua sendo o <aside> da página.
        (() => {
            const drawer = document.getElementById('nav-admin');

            if (!drawer) {
                return;
            }

            drawer.addEventListener('open.hs.overlay', () => {
                drawer.setAttribute('role', 'dialog');
                drawer.setAttribute('aria-modal', 'true');
            });

            drawer.addEventListener('close.hs.overlay', () => {
                drawer.removeAttribute('role');
                drawer.removeAttribute('aria-modal');
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>

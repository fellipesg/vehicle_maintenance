@php
    $portal = match(auth()->user()?->user_type) {
        'garage' => ['label' => 'Garagem', 'route' => 'garage.dashboard'],
        'workshop' => ['label' => 'Oficina', 'route' => 'workshop.dashboard'],
        default => ['label' => 'Usuário', 'route' => 'user.dashboard'],
    };
    $onAdminRoutes = auth()->user()?->isAdmin() && request()->routeIs('admin.*');
    $showAdminBadge = $onAdminRoutes;
    if ($onAdminRoutes) {
        $portal = ['label' => 'Admin', 'route' => 'admin.dashboard'];
    }
@endphp

<nav class="sticky top-0 z-50 border-b border-automotive-800 bg-automotive-950 text-white shadow-lg">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
        <div class="flex items-center gap-6">
            <a href="{{ auth()->check() ? route($portal['route']) : route('home') }}" class="flex items-center gap-2 font-bold">
                <img
                    src="{{ \App\Support\AppStorage::brandUrl('app-icon.png') }}"
                    alt="RevisaLog"
                    class="h-9 w-9 shrink-0 sm:hidden"
                >
                <img
                    src="{{ \App\Support\AppStorage::brandUrl('lockup-horizontal.png') }}"
                    alt="RevisaLog"
                    class="hidden h-9 w-auto sm:block"
                >
            </a>

            @auth
                <div class="hidden items-center gap-4 md:flex">
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="text-sm text-automotive-300 hover:text-wrench-400">Painel Admin</a>
                    @endif
                    @if(auth()->user()->isUser())
                        @include('layouts.partials.nav-user')
                    @elseif(auth()->user()->isGarage())
                        @include('layouts.partials.nav-garage')
                    @elseif(auth()->user()->isWorkshop())
                        @include('layouts.partials.nav-workshop')
                    @endif
                </div>
            @endauth
        </div>

        <div class="flex items-center gap-4">
            @auth
                <a href="{{ route('vehicle.search') }}" class="hidden text-sm text-automotive-300 hover:text-wrench-400 sm:inline">
                    Buscar veículo
                </a>
            @endauth

            @auth
                <x-notification-bell />
                @if($showAdminBadge)
                    <span class="badge badge-orange hidden sm:inline-flex">Admin</span>
                @else
                    <span class="badge badge-orange hidden sm:inline-flex">{{ $portal['label'] }}</span>
                @endif
                <span class="text-sm text-automotive-300">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-automotive-400 hover:text-wrench-400">Sair</button>
                </form>
            @else
                @if (request()->routeIs('home'))
                    <a href="#como-funciona" class="text-sm text-automotive-300 hover:text-wrench-400 max-md:hidden">Como funciona</a>
                    <a href="#recursos" class="text-sm text-automotive-300 hover:text-wrench-400 max-lg:hidden">Recursos</a>
                    <a href="#telas" class="text-sm text-automotive-300 hover:text-wrench-400 max-lg:hidden">Telas</a>
                    <a href="#preco" class="text-sm text-automotive-300 hover:text-wrench-400 max-md:hidden">Preço</a>
                @endif
                <a href="{{ route('login') }}" class="text-sm text-automotive-300 hover:text-wrench-400">Entrar</a>
                <a href="{{ route('register') }}" class="btn-primary !py-1.5 !text-xs">Cadastrar</a>
            @endauth
        </div>
    </div>
</nav>

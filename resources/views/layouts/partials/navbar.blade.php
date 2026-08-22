@php
    $portal = match(auth()->user()?->user_type) {
        'garage' => ['label' => 'Garagem', 'route' => 'garage.dashboard'],
        'workshop' => ['label' => 'Oficina', 'route' => 'workshop.dashboard'],
        default => ['label' => 'Usuário', 'route' => 'user.dashboard'],
    };
    $showAdminBadge = auth()->user()?->isAdmin() && request()->routeIs('admin.*');
@endphp

<nav class="border-b border-automotive-800 bg-automotive-950 text-white shadow-lg">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
        <div class="flex items-center gap-6">
            <a href="{{ route($portal['route']) }}" class="flex items-center gap-2 font-bold">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-automotive-800 text-wrench-500">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                        <path d="M5 11h1.6l1.1-3.2A1.4 1.4 0 0 1 9.02 7h5.96a1.4 1.4 0 0 1 1.32.8L17.4 11H19a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-1.1a2.4 2.4 0 1 1-4.8 0H9.9a2.4 2.4 0 1 1-4.8 0H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h1V11Z"/>
                        <path d="M15.5 3.5 14 5l1.5 1.5L14 8l1.5 1.5L17 8l1.5 1.5L20 8l-1.5-1.5L20 5l-1.5-1.5L17 5l-1.5-1.5Z"/>
                    </svg>
                </span>
                <span class="hidden sm:inline">Vehicle Maintenance</span>
            </a>

            @auth
                <div class="hidden items-center gap-4 md:flex">
                @if(auth()->user()->isAdmin())
                    @include('layouts.partials.nav-admin')
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
            <a href="{{ route('vehicle.search') }}" class="hidden text-sm text-automotive-300 hover:text-wrench-400 sm:inline">
                Buscar veículo
            </a>

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
                <a href="{{ route('login') }}" class="text-sm text-automotive-300 hover:text-wrench-400">Entrar</a>
                <a href="{{ route('register') }}" class="btn-primary !py-1.5 !text-xs">Cadastrar</a>
            @endauth
        </div>
    </div>
</nav>

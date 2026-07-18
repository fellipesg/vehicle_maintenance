@php
    $portal = match(auth()->user()?->user_type) {
        'garage' => ['label' => 'Garagem', 'route' => 'garage.dashboard', 'color' => 'emerald'],
        'workshop' => ['label' => 'Oficina', 'route' => 'workshop.dashboard', 'color' => 'orange'],
        default => ['label' => 'Usuário', 'route' => 'user.dashboard', 'color' => 'blue'],
    };
    $showAdminBadge = auth()->user()?->isAdmin() && request()->routeIs('admin.*');
@endphp

<nav class="border-b border-automotive-800 bg-automotive-950 text-white shadow-lg">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
        <div class="flex items-center gap-6">
            <a href="{{ route($portal['route']) }}" class="flex items-center gap-2 font-bold">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-wrench-500 text-base">🚗</span>
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
            <a href="{{ route('vehicle.search') }}" class="hidden text-sm text-automotive-300 hover:text-white sm:inline">
                🔍 Buscar veículo
            </a>

            @auth
                @if($showAdminBadge)
                    <span class="badge badge-orange hidden sm:inline-flex">Admin</span>
                @else
                    <span class="badge badge-orange hidden sm:inline-flex">{{ $portal['label'] }}</span>
                @endif
                <span class="text-sm text-automotive-300">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-automotive-400 hover:text-white">Sair</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-sm hover:text-wrench-400">Entrar</a>
                <a href="{{ route('register') }}" class="btn-primary !py-1.5 !text-xs">Cadastrar</a>
            @endauth
        </div>
    </div>
</nav>

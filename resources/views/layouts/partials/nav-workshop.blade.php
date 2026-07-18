<a href="{{ route('workshop.dashboard') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('workshop.dashboard') ? '!text-wrench-400' : '' }}">Dashboard</a>
<a href="{{ route('workshop.profile.show') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('workshop.profile.*') ? '!text-wrench-400' : '' }}">Minha Oficina</a>
<a href="{{ route('workshop.maintenances.index') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('workshop.maintenances.*') ? '!text-wrench-400' : '' }}">Serviços</a>

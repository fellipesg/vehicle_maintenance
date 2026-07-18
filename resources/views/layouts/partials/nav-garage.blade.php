<a href="{{ route('garage.dashboard') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('garage.dashboard') ? '!text-wrench-400' : '' }}">Dashboard</a>
<a href="{{ route('garage.vehicles.index') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('garage.vehicles.*') ? '!text-wrench-400' : '' }}">Estoque</a>
<a href="{{ route('garage.maintenances.index') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('garage.maintenances.*') ? '!text-wrench-400' : '' }}">Manutenções</a>

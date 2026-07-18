<a href="{{ route('user.dashboard') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('user.dashboard') ? '!text-wrench-400' : '' }}">Dashboard</a>
<a href="{{ route('user.vehicles.index') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('user.vehicles.*') ? '!text-wrench-400' : '' }}">Meus Veículos</a>
<a href="{{ route('user.maintenances.index') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('user.maintenances.*') ? '!text-wrench-400' : '' }}">Manutenções</a>
<a href="{{ route('user.workshops.index') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('user.workshops.*') ? '!text-wrench-400' : '' }}">Oficinas</a>

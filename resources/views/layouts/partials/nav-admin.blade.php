<a href="{{ route('admin.dashboard') }}" class="text-sm text-automotive-300 hover:text-wrench-400 {{ request()->routeIs('admin.dashboard') ? '!text-wrench-400' : '' }}">Dashboard</a>
<a href="{{ route('admin.vehicles.index') }}" class="text-sm text-automotive-300 hover:text-wrench-400 {{ request()->routeIs('admin.vehicles.*') ? '!text-wrench-400' : '' }}">Veículos</a>
<a href="{{ route('admin.maintenances.index') }}" class="text-sm text-automotive-300 hover:text-wrench-400 {{ request()->routeIs('admin.maintenances.*') ? '!text-wrench-400' : '' }}">Manutenções</a>
<a href="{{ route('admin.workshops.index') }}" class="text-sm text-automotive-300 hover:text-wrench-400 {{ request()->routeIs('admin.workshops.*') ? '!text-wrench-400' : '' }}">Oficinas</a>
<a href="{{ route('admin.maps.workshops') }}" class="text-sm text-automotive-300 hover:text-wrench-400 {{ request()->routeIs('admin.maps.workshops') ? '!text-wrench-400' : '' }}">Mapa oficinas</a>
<a href="{{ route('admin.maps.users') }}" class="text-sm text-automotive-300 hover:text-wrench-400 {{ request()->routeIs('admin.maps.users') ? '!text-wrench-400' : '' }}">Mapa usuários</a>
<a href="{{ route('admin.brands.index') }}" class="text-sm text-automotive-300 hover:text-wrench-400 {{ request()->routeIs('admin.brands.*') ? '!text-wrench-400' : '' }}">Marcas</a>

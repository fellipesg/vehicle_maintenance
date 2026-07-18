<a href="{{ route('admin.dashboard') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('admin.dashboard') ? '!text-wrench-400' : '' }}">⚙️ Admin</a>
<a href="{{ route('admin.brands.index') }}" class="text-sm text-automotive-300 hover:text-white {{ request()->routeIs('admin.brands.*') ? '!text-wrench-400' : '' }}">Marcas</a>

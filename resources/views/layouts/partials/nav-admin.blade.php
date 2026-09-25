@php
    $itemClass = function (bool $active): string {
        return $active
            ? 'flex items-center gap-2 rounded-lg bg-wrench-500/15 px-3 py-2 text-sm font-medium text-wrench-400 ring-1 ring-inset ring-wrench-500/25'
            : 'flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-automotive-300 hover:bg-automotive-900 hover:text-wrench-400';
    };
@endphp

<nav class="space-y-6" aria-label="Menu admin">
    <div>
        <p class="mb-2 px-3 text-[0.65rem] font-semibold uppercase tracking-wider text-automotive-500">Visão</p>
        <ul class="space-y-0.5">
            <li>
                <a href="{{ route('admin.dashboard') }}" class="{{ $itemClass(request()->routeIs('admin.dashboard')) }}">
                    Dashboard
                </a>
            </li>
        </ul>
    </div>

    <div>
        <p class="mb-2 px-3 text-[0.65rem] font-semibold uppercase tracking-wider text-automotive-500">Plataforma</p>
        <ul class="space-y-0.5">
            <li>
                <a
                    href="{{ route('admin.dashboard') }}#usuarios"
                    class="{{ $itemClass(request()->routeIs('admin.users.*')) }}"
                >
                    Usuários
                </a>
            </li>
            <li>
                <a href="{{ route('admin.vehicles.index') }}" class="{{ $itemClass(request()->routeIs('admin.vehicles.*')) }}">
                    Veículos
                </a>
            </li>
            <li>
                <a href="{{ route('admin.maintenances.index') }}" class="{{ $itemClass(request()->routeIs('admin.maintenances.*')) }}">
                    Manutenções
                </a>
            </li>
            <li>
                <a href="{{ route('admin.workshops.index') }}" class="{{ $itemClass(request()->routeIs('admin.workshops.*')) }}">
                    Oficinas
                </a>
            </li>
            <li>
                <a href="{{ route('admin.consignments.index') }}" class="{{ $itemClass(request()->routeIs('admin.consignments.*')) }}">
                    Consignações
                </a>
            </li>
        </ul>
    </div>

    <div>
        <p class="mb-2 px-3 text-[0.65rem] font-semibold uppercase tracking-wider text-automotive-500">Mapas</p>
        <ul class="space-y-0.5">
            <li>
                <a href="{{ route('admin.maps.workshops') }}" class="{{ $itemClass(request()->routeIs('admin.maps.workshops')) }}">
                    Oficinas
                </a>
            </li>
            <li>
                <a href="{{ route('admin.maps.users') }}" class="{{ $itemClass(request()->routeIs('admin.maps.users')) }}">
                    Usuários
                </a>
            </li>
        </ul>
    </div>

    <div>
        <p class="mb-2 px-3 text-[0.65rem] font-semibold uppercase tracking-wider text-automotive-500">Conteúdo</p>
        <ul class="space-y-0.5">
            <li>
                <a
                    href="{{ route('admin.blog.index') }}"
                    class="{{ $itemClass(request()->routeIs('admin.blog.index') || request()->routeIs('admin.blog.create') || request()->routeIs('admin.blog.edit')) }}"
                >
                    Blog
                </a>
            </li>
            <li>
                <a
                    href="{{ route('admin.blog.categories.index') }}"
                    class="{{ $itemClass(request()->routeIs('admin.blog.categories.*')) }}"
                >
                    Categorias
                </a>
            </li>
        </ul>
    </div>

    <div>
        <p class="mb-2 px-3 text-[0.65rem] font-semibold uppercase tracking-wider text-automotive-500">Catálogo</p>
        <ul class="space-y-0.5">
            <li>
                <a href="{{ route('admin.brands.index') }}" class="{{ $itemClass(request()->routeIs('admin.brands.*')) }}">
                    Marcas
                </a>
            </li>
        </ul>
    </div>
</nav>

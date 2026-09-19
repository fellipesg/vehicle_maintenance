<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Revisalog</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <x-brand-head-icons />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-automotive-50 font-sans text-automotive-900 antialiased">
    <input type="checkbox" id="admin-sidebar-toggle" class="peer sr-only" aria-hidden="true">

    <label
        for="admin-sidebar-toggle"
        class="fixed inset-0 z-40 hidden bg-automotive-950/50 peer-checked:block md:hidden"
        aria-label="Fechar menu"
    ></label>

    <div class="flex h-screen overflow-hidden">
        <aside
            id="nav-admin"
            class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col bg-automotive-950 text-white shadow-xl transition-transform duration-200 ease-out peer-checked:translate-x-0 md:static md:z-auto md:translate-x-0"
            aria-label="Navegação administrativa"
        >
            <div class="flex h-14 shrink-0 items-center border-b border-automotive-800 px-4">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                    <img src="{{ \App\Support\AppStorage::brandUrl('app-icon.png') }}" alt="" class="h-8 w-8 shrink-0">
                    <span class="font-semibold tracking-tight">Revisalog</span>
                </a>
            </div>

            <div class="flex-1 overflow-y-auto px-3 py-4">
                @include('layouts.partials.nav-admin')
            </div>

            <div class="shrink-0 border-t border-automotive-800 px-4 py-3 text-sm text-automotive-400">
                <span class="badge badge-orange mb-2 inline-flex">Admin</span>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="text-automotive-300 hover:text-wrench-400">Sair</button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-automotive-200 bg-white px-4 md:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <label
                        for="admin-sidebar-toggle"
                        class="inline-flex cursor-pointer rounded-lg p-2 text-automotive-700 hover:bg-automotive-100 md:hidden"
                        aria-label="Abrir menu"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </label>
                    <h1 class="truncate text-base font-semibold text-automotive-900 md:text-lg">
                        @yield('page_heading', trim($__env->yieldContent('title')))
                    </h1>
                </div>

                <div class="flex shrink-0 items-center gap-3 text-sm">
                    <a href="{{ route('vehicle.search') }}" class="hidden text-automotive-600 hover:text-wrench-600 sm:inline">
                        Buscar veículo
                    </a>
                    <span class="badge badge-orange hidden sm:inline-flex">Admin</span>
                    <span class="hidden max-w-[10rem] truncate text-automotive-600 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="text-automotive-500 hover:text-wrench-600">Sair</button>
                    </form>
                </div>
            </header>

            <main class="@yield('admin_main_class', 'flex-1 overflow-y-auto')">
                @if(session('success'))
                    <div class="@yield('admin_flash_wrapper_class', 'mx-auto w-full max-w-7xl px-4 pt-4 md:px-6')">
                        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            {{ session('success') }}
                        </div>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="@yield('admin_flash_wrapper_class', 'mx-auto w-full max-w-7xl px-4 pt-4 md:px-6')">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {{ session('warning') }}
                        </div>
                    </div>
                @endif

                @if(session('info'))
                    <div class="@yield('admin_flash_wrapper_class', 'mx-auto w-full max-w-7xl px-4 pt-4 md:px-6')">
                        <div class="rounded-lg border border-automotive-300 bg-automotive-100 px-4 py-3 text-sm text-automotive-800">
                            {{ session('info') }}
                        </div>
                    </div>
                @endif

                <div class="@yield('admin_content_wrapper_class', 'mx-auto w-full max-w-7xl px-4 py-6 md:px-6')">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
    <script>
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            const toggle = document.getElementById('admin-sidebar-toggle');
            if (toggle && toggle.checked) {
                toggle.checked = false;
            }
        });
    </script>
</body>
</html>

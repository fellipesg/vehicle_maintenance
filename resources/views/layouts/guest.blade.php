<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Entrar') — Vehicle Maintenance</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-automotive-950 font-sans text-white antialiased">
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-automotive-800 via-automotive-950 to-automotive-950"></div>
        <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-wrench-500/10 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-automotive-700/20 blur-3xl"></div>

        <div class="relative w-full max-w-md">
            <div class="mb-8 text-center">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-2xl font-bold">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-automotive-800 text-wrench-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6" aria-hidden="true">
                            <path d="M5 11h1.6l1.1-3.2A1.4 1.4 0 0 1 9.02 7h5.96a1.4 1.4 0 0 1 1.32.8L17.4 11H19a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-1.1a2.4 2.4 0 1 1-4.8 0H9.9a2.4 2.4 0 1 1-4.8 0H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h1V11Z"/>
                            <path d="M15.5 3.5 14 5l1.5 1.5L14 8l1.5 1.5L17 8l1.5 1.5L20 8l-1.5-1.5L20 5l-1.5-1.5L17 5l-1.5-1.5Z"/>
                        </svg>
                    </span>
                    Vehicle Maintenance
                </a>
                <p class="mt-2 text-sm text-automotive-300">Histórico completo de manutenções veiculares</p>
            </div>

            <div class="rounded-2xl border border-automotive-700/50 bg-automotive-900/80 p-8 shadow-2xl backdrop-blur">
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>

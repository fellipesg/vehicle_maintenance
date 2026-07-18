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
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-automotive-700 via-automotive-950 to-automotive-950"></div>
        <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-wrench-500/10 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-automotive-500/20 blur-3xl"></div>

        <div class="relative w-full max-w-md">
            <div class="mb-8 text-center">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-2xl font-bold">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-wrench-500 text-lg">🚗</span>
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

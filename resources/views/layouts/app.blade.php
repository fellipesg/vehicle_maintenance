<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Vehicle Maintenance') — Histórico de Manutenções</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-automotive-50 font-sans text-automotive-900 antialiased">
    <div class="flex min-h-screen flex-col">
        @include('layouts.partials.navbar')

        @if(session('success'))
            <div class="mx-auto w-full max-w-7xl px-4 pt-4">
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-auto w-full max-w-7xl px-4 pt-4">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ session('warning') }}
                </div>
            </div>
        @endif

        @if(session('info'))
            <div class="mx-auto w-full max-w-7xl px-4 pt-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    {{ session('info') }}
                </div>
            </div>
        @endif

        <main class="flex-1">
            @yield('content')
        </main>

        @include('layouts.partials.footer')
    </div>
    @stack('scripts')
</body>
</html>

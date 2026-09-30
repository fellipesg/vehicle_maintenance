<!DOCTYPE html>
<html lang="pt-BR" class="motion-safe:scroll-smooth">
<head>
    @php
        // Área atual (App\Enums\Portal) ou null para visitante. A navbar, o menu mobile e o menu de
        // conta leem esta mesma variável.
        $shellPortal = \App\Enums\Portal::current(auth()->user());
        // Nas telas da área logada o <title> leva a área ("Início · Proprietário · RevisaLog"); nas
        // públicas (home, blog, termos), mesmo logado, fica "{Página} · RevisaLog".
        $isPortalPage = $shellPortal !== null
            && request()->routeIs('user.*', 'garage.*', 'workshop.*', 'admin.*', 'account.*', 'notifications.*', 'vehicle.search');
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{!! \App\Support\DocumentTitle::compose($__env->yieldContent('title'), $isPortalPage ? $shellPortal->titleLabel() : null) !!}</title>
    {{-- Imagem de compartilhamento: a da página (@section('og_image'), a capa do post) ou a da marca. --}}
    <x-brand-head-icons include-og-image :og-image="$__env->yieldContent('og_image')" :og-image-alt="$__env->yieldContent('og_image_alt')" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
    @include('layouts.partials.skip-link')

    <div class="flex min-h-screen flex-col">
        @include('layouts.partials.navbar')

        <main id="conteudo" tabindex="-1" class="flex-1">
            @include('layouts.partials.flash', ['flashWrapperClass' => 'mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6'])

            @yield('content')
        </main>

        @guest
            @include('layouts.partials.footer')
        @else
            @include('layouts.partials.footer-compact')
        @endguest
    </div>

    {{-- Uma vez por layout: toasts (session('toast') e window.revisalogToast) e o diálogo usado por data-confirm. --}}
    <x-ui.toaster />
    <x-ui.confirm-dialog />

    @include('layouts.partials.shell-script')
    @stack('scripts')
</body>
</html>

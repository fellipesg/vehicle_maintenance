<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{!! \App\Support\DocumentTitle::compose($__env->yieldContent('title') ?: 'Entrar') !!}</title>
    <x-brand-head-icons include-og-image />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <x-analytics />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
{{-- [color-scheme:dark]: barra de rolagem, seletor de data e controles nativos no tom escuro. --}}
<body class="theme-inverse min-h-screen bg-background font-sans text-foreground antialiased [color-scheme:dark]">
    @include('layouts.partials.skip-link')

    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-12">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-automotive-800 via-automotive-950 to-automotive-950" aria-hidden="true"></div>
        <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-wrench-500/10 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-automotive-700/20 blur-3xl" aria-hidden="true"></div>

        <main id="conteudo" tabindex="-1" class="relative w-full max-w-md">
            <div class="mb-8 flex w-full justify-center">
                {{-- O lockup PNG não tem canal alfa (fundo automotive-950 embutido): o contêiner da mesma
                     cor, com anel, torna o retângulo intencional sobre o gradiente. --}}
                <a href="{{ route('home') }}" class="inline-flex items-center justify-center rounded-xl bg-automotive-950 px-4 py-3 ring-1 ring-automotive-800">
                    <img
                        src="{{ \App\Support\AppStorage::brandUrl('lockup-horizontal-tagline.png') }}"
                        alt="RevisaLog"
                        class="h-14 w-auto max-w-full"
                    >
                </a>
            </div>

            @include('layouts.partials.flash', ['flashWrapperClass' => 'mb-4'])

            {{-- O preenchimento automático do navegador pinta o campo de claro: a sombra interna devolve
                 o fundo escuro (surface) e o texto branco. p-6 no celular deixa o formulário respirar em 320px. --}}
            <div class="rounded-2xl border border-automotive-700/50 bg-automotive-900/80 p-6 shadow-2xl backdrop-blur sm:p-8 [&_input:autofill]:shadow-[inset_0_0_0_1000px_var(--color-surface)] [&_input:autofill]:[-webkit-text-fill-color:var(--color-foreground)] [&_input:autofill]:caret-foreground">
                @yield('content')
            </div>
        </main>

        <footer class="relative mt-8 w-full max-w-md">
            <nav aria-label="Rodapé">
                <ul class="flex flex-wrap items-center justify-center gap-x-3 text-sm text-muted-foreground">
                    <li><a href="{{ route('home') }}" class="inline-flex min-h-10 items-center rounded-sm px-1 underline-offset-4 transition-colors duration-fast ease-smooth-out hover:text-foreground hover:underline motion-reduce:transition-none">Voltar ao site</a></li>
                    <li><a href="{{ route('legal.terms') }}" class="inline-flex min-h-10 items-center rounded-sm px-1 underline-offset-4 transition-colors duration-fast ease-smooth-out hover:text-foreground hover:underline motion-reduce:transition-none">Termos de uso</a></li>
                    <li><a href="{{ route('legal.privacy') }}" class="inline-flex min-h-10 items-center rounded-sm px-1 underline-offset-4 transition-colors duration-fast ease-smooth-out hover:text-foreground hover:underline motion-reduce:transition-none">Privacidade</a></li>
                    <li><a href="{{ route('contact.show') }}" class="inline-flex min-h-10 items-center rounded-sm px-1 underline-offset-4 transition-colors duration-fast ease-smooth-out hover:text-foreground hover:underline motion-reduce:transition-none">Ajuda</a></li>
                </ul>
            </nav>
        </footer>
    </div>

    {{-- Uma vez por layout, como em layouts.app e layouts.admin: toasts (session('toast') e
         window.revisalogToast) e o diálogo usado por data-confirm. --}}
    <x-ui.toaster />
    <x-ui.confirm-dialog />

    @include('layouts.partials.shell-script')
    @stack('scripts')
</body>
</html>

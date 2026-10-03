{{--
    Layout das páginas de erro (403, 404, 419, 429, 500, 503), no visual do guest: fundo escuro
    (.theme-inverse), lockup da marca, cartão com código, título, explicação e ações.

    Sem sino, menu nem consultas além da conta logada, e nem essa nos erros 5xx (o banco pode ser a
    causa do erro). A 404 de uma URL que não existe roda fora da sessão: ali a conta não é
    conhecida e o botão leva ao site.

    Seções: title (o <title> e o H1), code (ex.: 404), icon (nome Heroicons), message (explicação)
    e actions (troca os botões padrão: Início da conta ou site + Fale com a gente).
--}}
@php
    $errorStatus = isset($exception) && method_exists($exception, 'getStatusCode') ? (int) $exception->getStatusCode() : 500;
    $errorUser = $errorStatus < 500 ? rescue(fn () => auth()->user(), null, false) : null;
    $errorHomeUrl = $errorUser !== null
        ? rescue(fn () => \App\Support\PortalAccess::homeUrl($errorUser), url('/'), false)
        : url('/');
    $errorHomeLabel = $errorUser !== null ? 'Ir para o Início' : 'Voltar ao site';
    $errorContactUrl = rescue(fn () => route('contact.show'), url('/contato'), false);
    $errorIcon = trim($__env->yieldContent('icon')) ?: 'exclamation-triangle';
    $errorCode = trim($__env->yieldContent('code')) ?: (string) $errorStatus;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="dark">
    <title>{!! \App\Support\DocumentTitle::compose($__env->yieldContent('title')) !!}</title>
    <x-brand-head-icons />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="theme-inverse min-h-screen bg-automotive-950 font-sans text-white antialiased [color-scheme:dark]">
    <a href="#conteudo" class="skip-link">Pular para o conteúdo</a>

    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-12">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-automotive-800 via-automotive-950 to-automotive-950" aria-hidden="true"></div>
        <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-wrench-500/10 blur-3xl" aria-hidden="true"></div>

        <main id="conteudo" tabindex="-1" class="relative w-full max-w-lg">
            <div class="mb-8 flex w-full justify-center">
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center rounded-xl bg-automotive-950 px-4 py-3 ring-1 ring-automotive-800">
                    <img
                        src="{{ \App\Support\AppStorage::brandUrl('lockup-horizontal-tagline.png') }}"
                        alt="RevisaLog"
                        class="h-14 w-auto max-w-full"
                    >
                </a>
            </div>

            <div data-error-code="{{ $errorCode }}" class="rounded-2xl border border-automotive-700/50 bg-automotive-900/80 p-6 text-center shadow-2xl backdrop-blur sm:p-8">
                <span class="mx-auto mb-4 inline-flex size-14 items-center justify-center rounded-xl border border-border-strong bg-surface-muted/50 text-accent-foreground">
                    <x-ui.icon :name="$errorIcon" class="size-7" />
                </span>
                <p class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">Erro {{ $errorCode }}</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-balance text-foreground">@yield('title')</h1>
                <div class="mx-auto mt-3 max-w-prose space-y-2 text-sm leading-6 text-muted-foreground">
                    @yield('message')
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                    @hasSection('actions')
                        @yield('actions')
                    @else
                        <x-ui.button :href="$errorContactUrl" variant="secondary" icon="chat-bubble-left-right">Fale com a gente</x-ui.button>
                        <x-ui.button :href="$errorHomeUrl" icon="home">{{ $errorHomeLabel }}</x-ui.button>
                    @endif
                </div>
            </div>
        </main>

        <footer class="relative mt-8 w-full max-w-lg">
            <nav aria-label="Rodapé">
                <ul class="flex flex-wrap items-center justify-center gap-x-3 text-sm text-muted-foreground">
                    <li><a href="{{ url('/') }}" class="inline-flex min-h-10 items-center rounded-sm px-1 underline-offset-4 hover:text-foreground hover:underline">Voltar ao site</a></li>
                    <li><a href="{{ url('/termos') }}" class="inline-flex min-h-10 items-center rounded-sm px-1 underline-offset-4 hover:text-foreground hover:underline">Termos de uso</a></li>
                    <li><a href="{{ url('/privacidade') }}" class="inline-flex min-h-10 items-center rounded-sm px-1 underline-offset-4 hover:text-foreground hover:underline">Privacidade</a></li>
                    <li><a href="{{ $errorContactUrl }}" class="inline-flex min-h-10 items-center rounded-sm px-1 underline-offset-4 hover:text-foreground hover:underline">Ajuda</a></li>
                </ul>
            </nav>
        </footer>
    </div>
</body>
</html>

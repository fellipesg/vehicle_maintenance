{{--
    Avisos de sessão no topo do conteúdo, um por chave: error, warning, success, status e info.
    status é a chave do Password broker do Laravel ("Enviamos o link..."): a view não precisa
    mostrar session('status') de novo.

    - success, status e info: role="status" (anúncio educado).
    - warning e error: role="alert" (anúncio imediato).
    - Cada aviso tem ícone, prefixo só para leitor de tela ("Erro:", "Atenção:"...) e o botão
      "Fechar aviso" (data-flash-dismiss), que remove o aviso e leva o foco ao <main id="conteudo">.
    - As cores vêm dos papéis semânticos, então o mesmo aviso fica legível dentro de .theme-inverse
      (layout guest) sem variante escura.

    Props:
    - wrapperClass: classes do contêiner externo (padrão: largura do conteúdo com recuo).
    - messages: mapa chave => texto no lugar da sessão (ex.: para uma prévia).

    Ex.: <x-ui.flash wrapper-class="mb-4" />
--}}
@props([
    'wrapperClass' => 'mx-auto w-full max-w-7xl px-4 pt-4',
    'messages' => null,
])
@php
    $flashVariants = [
        'error' => [
            'role' => 'alert',
            'prefix' => 'Erro',
            'icon' => 'exclamation-circle',
            'surface' => 'border-danger/30 bg-danger-soft text-danger',
        ],
        'warning' => [
            'role' => 'alert',
            'prefix' => 'Atenção',
            'icon' => 'exclamation-triangle',
            'surface' => 'border-warning/30 bg-warning-soft text-warning',
        ],
        'success' => [
            'role' => 'status',
            'prefix' => 'Sucesso',
            'icon' => 'check-circle',
            'surface' => 'border-success/30 bg-success-soft text-success',
        ],
        'status' => [
            'role' => 'status',
            'prefix' => 'Sucesso',
            'icon' => 'check-circle',
            'surface' => 'border-success/30 bg-success-soft text-success',
        ],
        'info' => [
            'role' => 'status',
            'prefix' => 'Informação',
            'icon' => 'information-circle',
            'surface' => 'border-border-strong bg-info-soft text-info',
        ],
    ];
    $flashSource = is_array($messages) ? $messages : null;
    $flashMessages = collect($flashVariants)
        ->map(fn (array $variant, string $key): array => $variant + ['message' => $flashSource !== null ? ($flashSource[$key] ?? null) : session($key)])
        ->filter(fn (array $variant): bool => is_scalar($variant['message']) && trim((string) $variant['message']) !== '');
@endphp
@if($flashMessages->isNotEmpty())
    <div {{ $attributes->class($wrapperClass) }}>
        <div class="space-y-3">
            @foreach($flashMessages as $flashKey => $flash)
                <div
                    role="{{ $flash['role'] }}"
                    data-flash="{{ $flashKey }}"
                    class="flex items-start gap-3 rounded-card border px-4 py-3 text-sm {{ $flash['surface'] }}"
                >
                    <x-ui.icon :name="$flash['icon']" class="mt-0.5 size-5" />
                    <p class="min-w-0 flex-1 self-center"><span class="sr-only">{{ $flash['prefix'] }}: </span>{{ $flash['message'] }}</p>
                    <button
                        type="button"
                        data-flash-dismiss
                        class="-my-2 -mr-2.5 inline-flex size-10 shrink-0 items-center justify-center rounded-control opacity-80 transition-opacity duration-fast hover:opacity-100 motion-reduce:transition-none"
                        aria-label="Fechar aviso"
                    >
                        <x-ui.icon name="x-mark" class="size-4" />
                    </button>
                </div>
            @endforeach
        </div>
    </div>
@endif

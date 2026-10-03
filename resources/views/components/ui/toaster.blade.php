{{--
    Região dos toasts (avisos curtos no canto da tela), desenhados por resources/js/ui/toast.js.
    Vai uma vez em cada layout: <x-ui.toaster />.

    Toast do servidor: session('toast') ou a prop toasts, em qualquer destes formatos:
    - 'Veículo salvo.' (texto vira o título, variante success);
    - ['title' => 'PDF pronto', 'description' => 'O arquivo está na lista de exportações.', 'variant' => 'info'];
    - uma lista desses arrays.
    Ex.: no layout, <x-ui.toaster />; no controller,
    return back()->with('toast', ['title' => 'Modelo ativado', 'variant' => 'success']);

    Toast do JavaScript: window.revisalogToast({ title, description, variant, duration }) ou
    import { toast } from './ui/toast'. Variantes: success (o padrão) | info | warning | error.

    Comportamento:
    - Leitor de tela: o texto é anunciado numa região aria-live polite; error usa a região
      assertiva (role="alert").
    - success e info fecham sozinhos em 5s; warning e error ficam até a pessoa fechar. O tempo pausa
      com o ponteiro ou o foco sobre os toasts e com a aba em segundo plano.
    - No máximo 3 na tela: o mais antigo sai.
    - Entrada de 200ms (opacidade e 8px de deslocamento); com prefers-reduced-motion, sem movimento.

    Não serve para erro de validação de formulário, que fica junto do campo.
--}}
@props([
    'toasts' => null,
])
@php
    $toasterVariants = ['success', 'info', 'warning', 'error'];
    $toasterSource = $toasts ?? session('toast');
    $toasterEntries = match (true) {
        is_string($toasterSource) => [$toasterSource],
        is_array($toasterSource) && (array_key_exists('title', $toasterSource) || array_key_exists('description', $toasterSource)) => [$toasterSource],
        is_array($toasterSource) => array_values($toasterSource),
        default => [],
    };
    $toasterInitial = collect($toasterEntries)
        ->map(fn (mixed $entry): mixed => is_string($entry) ? ['title' => $entry] : $entry)
        ->filter(fn (mixed $entry): bool => is_array($entry) && (filled($entry['title'] ?? null) || filled($entry['description'] ?? null)))
        ->map(fn (array $entry): array => [
            'title' => trim((string) ($entry['title'] ?? '')),
            'description' => trim((string) ($entry['description'] ?? '')),
            'variant' => in_array($entry['variant'] ?? null, $toasterVariants, true) ? $entry['variant'] : 'success',
        ])
        ->values()
        ->all();
@endphp
<div
    {{ $attributes->class('theme-default pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex flex-col items-stretch sm:inset-x-auto sm:right-4 sm:w-96') }}
    data-ui-toaster
    @if($toasterInitial !== []) data-toasts="{{ json_encode($toasterInitial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}" @endif
>
    <ol class="flex flex-col gap-2" data-ui-toast-list></ol>
    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-ui-toast-announcer="polite"></div>
    <div class="sr-only" role="alert" aria-live="assertive" aria-atomic="true" data-ui-toast-announcer="assertive"></div>
</div>

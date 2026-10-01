{{--
    Visualizador de fotos em tela escura (lightbox) com carrossel: <dialog> nativo aberto por
    showModal() (resources/js/ui/dialog.js: fundo inerte, Esc, foco preso e devolvido à miniatura) e
    resources/js/ui/lightbox.js para o carrossel.

    - As fotos ficam lado a lado numa trilha com scroll-snap: no celular a troca é o deslizar nativo
      do dedo; no teclado, as setas para os lados, Home e End; na tela, os botões "Foto anterior" e "Próxima
      foto" (aria-controls na trilha; nas pontas ficam aria-disabled, sem prender o foco).
    - Cada foto é um slide (role="group", aria-roledescription="slide") com o alt contextual da
      página e a legenda visível. O contador "Foto 2 de 4" fica no topo, e a região aria-live
      anuncia o alt da foto que entrou.
    - "Abrir original" leva sempre à foto da vez, em nova aba.
    - Movimento: o diálogo entra com opacidade e escala (200ms, ease-smooth-out) e a troca de foto
      rola suave; com prefers-reduced-motion, sem escala e com troca instantânea.

    Gatilhos: qualquer link com data-lightbox-open="{id}" e data-lightbox-index="{posição a partir
    de 0}", com href para a imagem e target="_blank" (sem JS abre a foto em nova aba). Um
    [data-lightbox-new-tab-hint] dentro do link ("(abre em nova aba)") some quando o JS assume.

    Props:
    - id (obrigatório).
    - title (obrigatório): título do diálogo ("Fotos do serviço").
    - items (obrigatório): lista de ['src' => url, 'alt' => texto alternativo contextual,
      'caption' => legenda visível opcional]. Use o mesmo alt na miniatura.
    - label: nome do carrossel (o padrão é o title).

    Ex.:
    <a href="{{ $photo->url }}" target="_blank" rel="noopener" data-lightbox-open="fotos-os-12" data-lightbox-index="0">
        <img src="{{ $photo->url }}" alt="Foto 1 de 4 — Troca de óleo, 12/03/2025. Carro antes do serviço" class="aspect-[4/3] w-full object-contain">
        <span class="sr-only" data-lightbox-new-tab-hint>(abre em nova aba)</span>
    </a>
    <x-ui.lightbox id="fotos-os-12" title="Fotos do serviço" :items="$photoItems" />
--}}
@props([
    'id' => null,
    'title' => null,
    'items' => [],
    'label' => null,
])
@php
    \App\Support\UiProps::required('x-ui.lightbox', 'id', $id);
    \App\Support\UiProps::required('x-ui.lightbox', 'title', $title, 'O título é o nome acessível do diálogo.');

    $lightboxItems = collect(is_iterable($items) ? $items : [])
        ->map(fn (mixed $item): array => [
            'src' => trim((string) (is_array($item) ? ($item['src'] ?? '') : $item)),
            'alt' => trim((string) (is_array($item) ? ($item['alt'] ?? '') : '')),
            'caption' => trim((string) (is_array($item) ? ($item['caption'] ?? '') : '')),
        ])
        ->filter(fn (array $item): bool => $item['src'] !== '')
        ->values();

    \App\Support\UiProps::required('x-ui.lightbox', 'items', $lightboxItems->isEmpty() ? null : 'ok', 'Passe ao menos uma foto com src.');

    foreach ($lightboxItems as $lightboxItem) {
        \App\Support\UiProps::required('x-ui.lightbox', 'alt', $lightboxItem['alt'], 'Cada foto precisa de alt contextual ("Foto 2 de 4 — serviço, data").');
    }

    $lightboxTotal = $lightboxItems->count();
    $lightboxFirst = $lightboxItems->first();
    $lightboxTitleId = $id.'-titulo';
    $lightboxTrackId = $id.'-trilho';
    $lightboxHasMany = $lightboxTotal > 1;
@endphp
<dialog
    id="{{ $id }}"
    {{ $attributes->class([
        'theme-inverse relative m-auto h-[min(calc(100dvh-1rem),52rem)] w-[calc(100%-1rem)] max-w-6xl overflow-hidden rounded-overlay border border-border bg-background p-0 text-foreground shadow-xl open:flex open:flex-col',
        'backdrop:bg-overlay',
        'opacity-0 open:opacity-100 starting:open:opacity-0',
        'motion-safe:scale-[.96] motion-safe:open:scale-100 motion-safe:starting:open:scale-[.96]',
        'transition-[opacity,scale,overlay,display] transition-discrete duration-fast ease-smooth-out open:duration-base',
        'backdrop:opacity-0 open:backdrop:opacity-100 starting:open:backdrop:opacity-0',
        'backdrop:transition-[opacity,overlay,display] backdrop:transition-discrete backdrop:duration-fast open:backdrop:duration-base',
        'motion-reduce:transition-none motion-reduce:backdrop:transition-none',
    ])->merge([
        'aria-labelledby' => $lightboxTitleId,
        'data-ui-dialog' => '',
        'data-ui-lightbox' => '',
        'data-dismissible' => 'true',
        'data-close-on-backdrop' => 'true',
    ]) }}
>
    <div class="flex shrink-0 items-center gap-2 border-b border-border py-2 pr-2 pl-4">
        <div class="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-3">
            <h2 id="{{ $lightboxTitleId }}" class="truncate text-base font-semibold text-foreground">{{ $title }}</h2>
            <p class="text-sm text-muted-foreground tabular-nums" aria-hidden="true" data-lightbox-counter>Foto 1 de {{ $lightboxTotal }}</p>
        </div>
        <a
            href="{{ $lightboxFirst['src'] }}"
            target="_blank"
            rel="noopener"
            class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-control px-3 text-sm font-medium text-link transition-colors duration-fast ease-smooth-out hover:bg-surface-muted hover:text-link-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
            data-lightbox-original
        >
            <span class="max-sm:sr-only">Abrir original</span>
            <x-ui.icon name="arrow-top-right-on-square" class="size-4" />
            <span class="sr-only">(abre em nova aba)</span>
        </a>
        <button
            type="button"
            data-dialog-close
            data-dialog-close-button
            @unless($lightboxHasMany) data-dialog-initial-focus @endunless
            class="inline-flex size-10 shrink-0 items-center justify-center rounded-control text-muted-foreground transition-colors duration-fast ease-smooth-out hover:bg-surface-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
            aria-label="Fechar"
        >
            <x-ui.icon name="x-mark" />
        </button>
    </div>

    <p class="sr-only" aria-live="polite" aria-atomic="true" data-lightbox-status></p>

    <div class="relative min-h-0 flex-1" role="region" aria-roledescription="carrossel" aria-label="{{ filled($label) ? $label : $title }}">
        <div
            id="{{ $lightboxTrackId }}"
            tabindex="-1"
            class="flex h-full snap-x snap-mandatory overflow-x-auto overflow-y-hidden overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            data-lightbox-track
        >
            @foreach($lightboxItems as $lightboxIndex => $lightboxItem)
                <div
                    role="group"
                    aria-roledescription="slide"
                    aria-label="Foto {{ $lightboxIndex + 1 }} de {{ $lightboxTotal }}"
                    @if($lightboxIndex > 0) aria-hidden="true" @endif
                    class="flex h-full w-full shrink-0 snap-center snap-always flex-col gap-3 px-4 py-4 sm:px-16"
                    data-lightbox-slide
                    data-lightbox-src="{{ $lightboxItem['src'] }}"
                    data-lightbox-alt="{{ $lightboxItem['alt'] }}"
                >
                    <div class="flex min-h-0 flex-1 items-center justify-center">
                        <img src="{{ $lightboxItem['src'] }}" alt="{{ $lightboxItem['alt'] }}" loading="lazy" decoding="async" class="size-full object-contain" draggable="false">
                    </div>
                    @if($lightboxItem['caption'] !== '')
                        <p class="shrink-0 text-center text-sm text-muted-foreground">{{ $lightboxItem['caption'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if($lightboxHasMany)
            <button
                type="button"
                aria-controls="{{ $lightboxTrackId }}"
                aria-label="Foto anterior"
                aria-disabled="true"
                class="absolute top-1/2 left-2 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background/80 text-foreground shadow-md backdrop-blur-sm transition-[background-color,opacity] duration-fast ease-smooth-out hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-disabled:opacity-40 aria-disabled:hover:bg-background/80 motion-reduce:transition-none"
                data-lightbox-prev
            >
                <x-ui.icon name="chevron-left" />
            </button>
            <button
                type="button"
                aria-controls="{{ $lightboxTrackId }}"
                aria-label="Próxima foto"
                class="absolute top-1/2 right-2 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-background/80 text-foreground shadow-md backdrop-blur-sm transition-[background-color,opacity] duration-fast ease-smooth-out hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-disabled:opacity-40 aria-disabled:hover:bg-background/80 motion-reduce:transition-none"
                data-lightbox-next
                data-dialog-initial-focus
            >
                <x-ui.icon name="chevron-right" />
            </button>
        @endif
    </div>

    @if($lightboxHasMany)
        <p class="shrink-0 border-t border-border px-4 py-2 text-center text-xs text-muted-foreground">Use as setas do teclado ou deslize para trocar de foto.</p>
    @endif
</dialog>

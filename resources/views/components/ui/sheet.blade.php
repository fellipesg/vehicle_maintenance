{{--
    Painel lateral (sheet) com o HSOverlay do Preline: fundo escuro, Esc e clique no fundo fecham,
    o Tab fica preso no painel e o foco volta ao gatilho ao fechar. resources/js/ui/preline.js
    completa o que o plugin não faz: Enter em link ou botão do painel e Shift+Tab no primeiro item.

    Props:
    - id (obrigatório): o gatilho é
      <button type="button" data-hs-overlay="#{id}" aria-controls="{id}" aria-expanded="false" aria-haspopup="dialog">.
      O aria-expanded="false" inicial é obrigatório: o Preline só atualiza o atributo se ele existir.
    - title (obrigatório): título visível e nome acessível (aria-labelledby).
    - description: texto de apoio ligado por aria-describedby.
    - side: right (o padrão) | left.
    - size: sm (18rem) | md (24rem, o padrão) | lg (32rem). Nunca passa da tela menos 3rem.
    - inverse: true usa a superfície escura da marca (theme-inverse), como o menu mobile.
    - closeFrom: sm | md | lg | xl. A partir dessa largura o painel aberto fecha sozinho (use quando
      o conteúdo vira barra ou sidebar fixa nesse breakpoint). Vira data-close-from, lido por
      resources/js/ui/preline.js com matchMedia.

    Slots: o padrão (corpo, com rolagem própria) e footer (fixo no rodapé do painel).

    Ex.:
    <x-ui.button variant="secondary" icon="funnel" data-hs-overlay="#filtros" aria-controls="filtros" aria-expanded="false" aria-haspopup="dialog">Filtros</x-ui.button>
    <x-ui.sheet id="filtros" title="Filtros" side="right" close-from="lg">
        ...campos...
        <x-slot:footer><x-ui.button type="submit" form="form-filtros" full>Aplicar filtros</x-ui.button></x-slot:footer>
    </x-ui.sheet>
--}}
@props([
    'id',
    'title',
    'description' => null,
    'side' => 'right',
    'size' => 'md',
    'inverse' => false,
    'closeFrom' => null,
])
@php
    \App\Support\UiProps::required('x-ui.sheet', 'id', $id);
    \App\Support\UiProps::required('x-ui.sheet', 'title', $title, 'O título é o nome acessível do painel.');
    $sheetSideClass = match (\App\Support\UiProps::oneOf('x-ui.sheet', 'side', $side, ['right', 'left'], 'right')) {
        'right' => 'end-0 translate-x-full border-s',
        'left' => 'start-0 -translate-x-full border-e',
    };
    $sheetSizeClass = match (\App\Support\UiProps::oneOf('x-ui.sheet', 'size', $size, ['sm', 'md', 'lg'], 'md')) {
        'sm' => 'w-[min(18rem,calc(100vw-3rem))]',
        'md' => 'w-[min(24rem,calc(100vw-3rem))]',
        'lg' => 'w-[min(32rem,calc(100vw-3rem))]',
    };
    $sheetCloseFrom = $closeFrom === null ? null : \App\Support\UiProps::oneOf('x-ui.sheet', 'closeFrom', $closeFrom, ['sm', 'md', 'lg', 'xl'], 'lg');
    $sheetTitleId = $id.'-titulo';
    $sheetDescriptionId = filled($description) ? $id.'-descricao' : null;
    // O merge() escapa o JSON (aspas viram &quot;) e o navegador devolve o texto original ao Preline.
    $sheetBackdropOptions = json_encode([
        'backdropClasses' => 'hs-overlay-backdrop fixed inset-0 bg-overlay transition-opacity duration-slow ease-smooth-out motion-reduce:transition-none',
    ], JSON_UNESCAPED_SLASHES);
@endphp
<div
    id="{{ $id }}"
    {{ $attributes->class([
        'hs-overlay hidden fixed inset-y-0 z-[60] h-dvh max-w-full border-border bg-surface text-foreground shadow-xl',
        $inverse ? 'theme-inverse' : 'theme-default',
        $sheetSideClass,
        $sheetSizeClass,
        'transition-transform duration-slow ease-smooth-out hs-overlay-open:translate-x-0 motion-reduce:transition-none',
    ])->merge([
        'role' => 'dialog',
        'aria-modal' => 'true',
        'aria-labelledby' => $sheetTitleId,
        'aria-describedby' => $sheetDescriptionId,
        'tabindex' => '-1',
        'data-ui-sheet' => '',
        'data-close-from' => $sheetCloseFrom,
        'data-hs-overlay-options' => $sheetBackdropOptions,
    ]) }}
>
    <div class="flex h-full flex-col">
        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-border px-4 py-3">
            <div class="min-w-0 pt-2">
                <h2 id="{{ $sheetTitleId }}" class="text-base font-semibold text-foreground">{{ $title }}</h2>
                @if($sheetDescriptionId)
                    <p id="{{ $sheetDescriptionId }}" class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>
                @endif
            </div>
            <button
                type="button"
                data-hs-overlay="#{{ $id }}"
                class="-mr-1 inline-flex size-11 shrink-0 items-center justify-center rounded-control text-muted-foreground transition-colors duration-fast hover:bg-surface-muted hover:text-foreground motion-reduce:transition-none"
                aria-label="Fechar"
            >
                <x-ui.icon name="x-mark" class="size-6" />
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
            {{ $slot }}
        </div>

        @isset($footer)
            <div {{ $footer->attributes->class('shrink-0 border-t border-border p-4 pb-[max(1rem,env(safe-area-inset-bottom))]') }}>
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>

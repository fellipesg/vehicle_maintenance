{{--
    Diálogo modal com <dialog> nativo aberto por showModal(): o navegador deixa o resto da página
    inerte, coloca o diálogo no top layer e fecha com Esc. resources/js/ui/dialog.js liga os
    gatilhos, fecha pelo fundo, define o foco inicial e devolve o foco a quem abriu.

    Props:
    - id (obrigatório): o gatilho é <button type="button" data-dialog-open="{id}">. O JS completa
      aria-haspopup="dialog" e aria-controls no gatilho.
    - title (obrigatório): título visível e nome acessível (aria-labelledby).
    - description: texto de apoio ligado por aria-describedby.
    - size: sm (max-w-sm) | md (max-w-lg, o padrão) | lg (max-w-2xl) | xl (max-w-4xl).
    - role: dialog (o padrão) | alertdialog (confirmação que interrompe a tarefa).
    - dismissible: true (o padrão) fecha com Esc. false: só pelos botões do diálogo.
    - closeOnBackdrop: fecha ao clicar fora. Segue dismissible quando omitido.
    - closeButton: botão "Fechar" (x) no canto. Segue dismissible quando omitido.

    Slots: o padrão (corpo) e footer (ações, com a principal por último: no celular elas empilham
    com a principal em cima). Qualquer elemento com data-dialog-close dentro do diálogo o fecha;
    data-dialog-close="valor" vira o returnValue.

    Foco inicial: o elemento com data-dialog-initial-focus ou autofocus; sem eles, o primeiro
    controle que não seja o "x". O "Fechar" (x) fica por último no DOM, no canto superior no visual.

    O diálogo usa sempre a superfície clara (theme-default), mesmo aberto a partir da navbar escura.

    Ex.:
    <x-ui.button variant="secondary" data-dialog-open="dialogo-placa">Trocar placa</x-ui.button>
    <x-ui.dialog id="dialogo-placa" title="Trocar placa" description="A placa antiga fica no histórico.">
        ...campos...
        <x-slot:footer>
            <x-ui.button variant="secondary" data-dialog-close>Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-placa">Salvar placa</x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>
--}}
@props([
    'id',
    'title',
    'description' => null,
    'size' => 'md',
    'role' => 'dialog',
    'dismissible' => true,
    'closeOnBackdrop' => null,
    'closeButton' => null,
])
@php
    \App\Support\UiProps::required('x-ui.dialog', 'id', $id);
    \App\Support\UiProps::required('x-ui.dialog', 'title', $title, 'O título é o nome acessível do diálogo.');
    $dialogSizeClass = match (\App\Support\UiProps::oneOf('x-ui.dialog', 'size', $size, ['sm', 'md', 'lg', 'xl'], 'md')) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    };
    $dialogRole = \App\Support\UiProps::oneOf('x-ui.dialog', 'role', $role, ['dialog', 'alertdialog'], 'dialog');
    $dialogIsDismissible = (bool) $dismissible;
    $dialogClosesOnBackdrop = (bool) ($closeOnBackdrop ?? $dialogIsDismissible);
    $dialogHasCloseButton = (bool) ($closeButton ?? $dialogIsDismissible);
    $dialogTitleId = $id.'-titulo';
    $dialogDescriptionId = filled($description) ? $id.'-descricao' : null;
    $dialogHasBody = trim((string) $slot) !== '';
@endphp
<dialog
    id="{{ $id }}"
    {{ $attributes->class([
        'theme-default relative m-auto w-[calc(100%-2rem)] max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-overlay border border-border bg-surface p-6 text-left text-foreground shadow-xl',
        $dialogSizeClass,
        'backdrop:bg-overlay',
        'opacity-0 open:opacity-100 starting:open:opacity-0',
        'motion-safe:scale-[.96] motion-safe:open:scale-100 motion-safe:starting:open:scale-[.96]',
        'transition-[opacity,scale,overlay,display] transition-discrete duration-fast ease-smooth-out open:duration-base',
        'backdrop:opacity-0 open:backdrop:opacity-100 starting:open:backdrop:opacity-0',
        'backdrop:transition-[opacity,overlay,display] backdrop:transition-discrete backdrop:duration-fast open:backdrop:duration-base',
        'motion-reduce:transition-none motion-reduce:backdrop:transition-none',
    ])->merge([
        'aria-labelledby' => $dialogTitleId,
        'aria-describedby' => $dialogDescriptionId,
        'role' => $dialogRole === 'alertdialog' ? 'alertdialog' : null,
        'data-ui-dialog' => '',
        'data-dismissible' => $dialogIsDismissible ? 'true' : 'false',
        'data-close-on-backdrop' => $dialogClosesOnBackdrop ? 'true' : 'false',
    ]) }}
>
    <div @class(['min-w-0', 'pr-10' => $dialogHasCloseButton])>
        <h2 id="{{ $dialogTitleId }}" class="text-lg font-semibold leading-snug text-foreground">{{ $title }}</h2>
        @if($dialogDescriptionId)
            <p id="{{ $dialogDescriptionId }}" class="mt-1.5 text-sm text-muted-foreground">{{ $description }}</p>
        @endif
    </div>

    @if($dialogHasBody)
        <div class="mt-4 text-sm text-foreground">
            {{ $slot }}
        </div>
    @endif

    @isset($footer)
        <div {{ $footer->attributes->class('mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end') }}>
            {{ $footer }}
        </div>
    @endisset

    @if($dialogHasCloseButton)
        <button
            type="button"
            data-dialog-close
            data-dialog-close-button
            class="absolute top-3 right-3 inline-flex size-10 items-center justify-center rounded-control text-muted-foreground transition-colors duration-fast hover:bg-surface-muted hover:text-foreground motion-reduce:transition-none"
            aria-label="Fechar"
        >
            <x-ui.icon name="x-mark" />
        </button>
    @endif
</dialog>

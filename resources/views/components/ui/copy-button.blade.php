{{--
    Botão de copiar um valor curto (chassi, código do selo, link de verificação, convite). Ao copiar,
    o ícone vira um check por 1,5s e a região role="status" anuncia "Copiado" ao leitor de tela; o
    nome do botão não muda. Se o navegador não liberar a área de transferência, aparece ao lado um
    campo só de leitura com o texto já selecionado e o aviso "Pressione Ctrl+C para copiar".
    resources/js/ui/copy.js faz o trabalho; sem JS o botão não aparece (o valor continua na tela).

    Props:
    - value (obrigatório): o texto copiado.
    - label (obrigatório): ação com o objeto, "Copiar chassi", "Copiar código", "Copiar link".
      É o texto visível ou, com iconOnly, o aria-label.
    - copiedLabel: o que o leitor de tela ouve depois de copiar (o padrão é "Copiado").
    - variant: secondary (o padrão) | ghost.
    - size: sm (o padrão; 32px, 40px de alvo no celular) | md (40px).
    - iconOnly: só o ícone, com o label no aria-label.

    Não use para dado que a pessoa não pode ver inteiro (chassi mascarado na busca pública).

    Ex.: <x-ui.copy-button :value="$vehicle->chassis" label="Copiar chassi" />
         <x-ui.copy-button :value="$maintenance->verificationUrl()" label="Copiar link" icon-only variant="ghost" />
--}}
@props([
    'value' => null,
    'label' => null,
    'copiedLabel' => 'Copiado',
    'variant' => 'secondary',
    'size' => 'sm',
    'iconOnly' => false,
])
@php
    \App\Support\UiProps::required('x-ui.copy-button', 'value', $value, 'É o texto que vai para a área de transferência.');
    \App\Support\UiProps::required('x-ui.copy-button', 'label', $label, 'Diga o que é copiado: "Copiar chassi".');

    $copyVariant = \App\Support\UiProps::oneOf('x-ui.copy-button', 'variant', $variant, ['secondary', 'ghost'], 'secondary');
    $copySize = \App\Support\UiProps::oneOf('x-ui.copy-button', 'size', $size, ['sm', 'md'], 'sm');
    $copyIconOnly = (bool) $iconOnly;
    $copyVariantClasses = match ($copyVariant) {
        'secondary' => 'border border-border-strong bg-surface text-foreground shadow-xs hover:bg-surface-muted',
        'ghost' => 'text-muted-foreground hover:bg-surface-muted hover:text-foreground',
    };
    $copySizeClasses = match (true) {
        $copyIconOnly && $copySize === 'sm' => 'size-8 after:absolute after:-inset-1',
        $copyIconOnly => 'size-10',
        $copySize === 'sm' => 'min-h-8 gap-1.5 px-3 py-1.5 text-xs max-sm:min-h-10',
        default => 'min-h-10 gap-2 px-4 py-2 text-sm',
    };
    $copyIconClass = $copySize === 'sm' ? 'size-4' : 'size-5';
@endphp
<span {{ $attributes->class(['inline-flex min-w-0 flex-wrap items-center gap-2'])->merge(['data-slot' => 'copy-button']) }}>
    <button
        type="button"
        hidden
        @class([
            'group/copy relative inline-flex shrink-0 items-center justify-center rounded-control font-semibold select-none',
            'transition-[color,background-color,border-color,box-shadow,scale] duration-fast ease-smooth-out motion-reduce:transition-none',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-safe:active:scale-[.98]',
            $copySizeClasses,
            $copyVariantClasses,
        ])
        data-copy-button
        data-copy-value="{{ $value }}"
        data-copied-label="{{ $copiedLabel }}"
        @if($copyIconOnly) aria-label="{{ $label }}" @endif
    >
        <span @class(['relative inline-grid shrink-0 place-items-center', $copyIconClass]) aria-hidden="true">
            <x-ui.icon name="clipboard-document" :class="[
                'col-start-1 row-start-1 transition-[opacity,scale] duration-fast ease-smooth-out motion-reduce:transition-none',
                'group-data-copied/copy:scale-50 group-data-copied/copy:opacity-0',
                $copyIconClass,
            ]" />
            <x-ui.icon name="check" :class="[
                'col-start-1 row-start-1 text-success opacity-0 motion-safe:scale-50',
                'transition-[opacity,scale] duration-fast ease-smooth-out motion-reduce:transition-none',
                'group-data-copied/copy:scale-100 group-data-copied/copy:opacity-100',
                $copyIconClass,
            ]" />
        </span>
        @unless($copyIconOnly)
            <span data-slot="label">{{ $label }}</span>
        @endunless
    </button>
    <span class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-copy-status></span>
    <input
        type="text"
        readonly
        hidden
        value="{{ $value }}"
        aria-label="{{ $label }}: texto para copiar"
        class="form-input h-10 w-auto min-w-0 flex-1 font-mono text-sm"
        data-copy-fallback
    >
</span>

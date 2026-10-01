{{--
    Botão do design system. Com href vira <a>; sem href é <button>.

    Props:
    - variant: primary (o padrão; no máximo um por tela ou formulário) | secondary | ghost | danger
      (ação destrutiva) | link (ação com cara de link, sem fundo).
    - size: sm (32px; abaixo de sm cresce para 40px de alvo) | md (40px, o padrão) | lg (44px, CTA e
      rodapé de formulário no celular).
    - href: renderiza <a>. Desabilitado ou em loading, o <a> perde o href e ganha role="link" e
      aria-disabled="true", para não navegar nem receber foco.
    - type: button (o padrão, não envia formulário sem querer) | submit | reset. Ignorado com href.
    - icon / icon-trailing: nome de ícone de x-ui.icon antes ou depois do rótulo (decorativo).
    - loading: troca o ícone por x-ui.spinner, aplica aria-busy="true" e desabilita o controle.
    - loading-label: rótulo durante o loading ("Salvando…"). Também sai em data-loading-label para o
      JS que liga o loading no envio do formulário.
    - disabled: desabilita (<button disabled> ou <a aria-disabled>).
    - full: ocupa a largura toda (w-full).

    Slot: o rótulo. Botão só com ícone usa x-ui.icon-button, que exige nome acessível.
    Atributos extras (name, value, form, data-*, aria-*) vão para o elemento. Foco visível pelo
    contorno --color-ring, que no .theme-inverse vira wrench-400.

    Ex.: <x-ui.button icon="plus" :href="route('user.vehicles.create')">Adicionar veículo</x-ui.button>
         <x-ui.button type="submit" loading-label="Salvando…">Salvar manutenção</x-ui.button>
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'iconTrailing' => null,
    'loading' => false,
    'loadingLabel' => null,
    'disabled' => false,
    'full' => false,
])
@php
    $buttonVariant = \App\Support\UiProps::oneOf('x-ui.button', 'variant', $variant, ['primary', 'secondary', 'ghost', 'danger', 'link'], 'primary');
    $buttonSize = \App\Support\UiProps::oneOf('x-ui.button', 'size', $size, ['sm', 'md', 'lg'], 'md');
    $buttonType = \App\Support\UiProps::oneOf('x-ui.button', 'type', $type, ['button', 'submit', 'reset'], 'button');
    $buttonIsLoading = (bool) $loading;
    $buttonIsDisabled = (bool) $disabled || $buttonIsLoading;
    $buttonIsLink = filled($href);
    $buttonLoadingLabel = filled($loadingLabel) ? (string) $loadingLabel : null;

    $buttonVariantClasses = match ($buttonVariant) {
        'primary' => 'bg-primary text-primary-foreground shadow-xs hover:bg-primary-hover',
        'secondary' => 'border border-border-strong bg-surface text-foreground shadow-xs hover:bg-surface-muted',
        'ghost' => 'text-foreground hover:bg-surface-muted',
        'danger' => 'bg-danger text-danger-foreground shadow-xs hover:bg-danger-hover',
        'link' => 'text-link underline-offset-4 hover:text-link-hover hover:underline',
    };
    $buttonSizeClasses = match ($buttonSize) {
        'sm' => 'min-h-8 gap-1.5 py-1.5 text-xs max-sm:min-h-10',
        'md' => 'min-h-10 gap-2 py-2 text-sm',
        'lg' => 'min-h-11 gap-2 py-2.5 text-base',
    };
    $buttonPaddingClasses = $buttonVariant === 'link' ? 'px-0' : match ($buttonSize) {
        'sm' => 'px-3',
        'md' => 'px-4',
        'lg' => 'px-5',
    };
    $buttonIconClass = $buttonSize === 'sm' ? 'size-4' : 'size-5';

    $buttonAttributes = $attributes
        ->class([
            'inline-flex items-center justify-center rounded-control text-center font-semibold select-none',
            'transition-[color,background-color,border-color,box-shadow,scale] duration-fast ease-smooth-out motion-reduce:transition-none',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
            'motion-safe:active:scale-[.98]',
            'disabled:pointer-events-none disabled:opacity-60 aria-disabled:pointer-events-none aria-disabled:opacity-60',
            $buttonSizeClasses,
            $buttonPaddingClasses,
            $buttonVariantClasses,
            'w-full' => (bool) $full,
        ])
        ->merge([
            'data-slot' => 'button',
            'data-variant' => $buttonVariant,
            'aria-busy' => $buttonIsLoading ? 'true' : null,
            'data-loading-label' => $buttonLoadingLabel,
        ]);

    $buttonAttributes = $buttonIsLink
        ? $buttonAttributes->merge([
            'href' => $buttonIsDisabled ? null : $href,
            'role' => $buttonIsDisabled ? 'link' : null,
            'aria-disabled' => $buttonIsDisabled ? 'true' : null,
        ])
        : $buttonAttributes->merge([
            'type' => $buttonType,
            'disabled' => $buttonIsDisabled,
        ]);
@endphp
<{{ $buttonIsLink ? 'a' : 'button' }} {{ $buttonAttributes }}>
    @if($buttonIsLoading)
        <x-ui.spinner :size="$buttonSize === 'sm' ? 'sm' : 'md'" />
    @elseif(filled($icon))
        <x-ui.icon :name="$icon" :class="$buttonIconClass" />
    @endif
    <span data-slot="label">@if($buttonIsLoading && $buttonLoadingLabel !== null){{ $buttonLoadingLabel }}@else{{ $slot }}@endif</span>
    @if(filled($iconTrailing))
        <x-ui.icon :name="$iconTrailing" :class="$buttonIconClass" />
    @endif
</{{ $buttonIsLink ? 'a' : 'button' }}>@php
    // Sem quebra de linha depois do elemento: o fechamento deste bloco PHP engole a do fim do
    // arquivo, e o componente não ganha espaço sobrando quando fica no meio do texto.
@endphp

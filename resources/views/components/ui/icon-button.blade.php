{{--
    Botão só com ícone (fechar, menu, sino, ações de linha). O nome acessível vem de label, que é
    obrigatório; o ícone é decorativo.

    Props:
    - icon: nome de ícone de x-ui.icon (obrigatório).
    - label: nome acessível (aria-label), em pt-BR e com o contexto: "Fechar aviso", "Abrir menu",
      "Notificações, 3 não lidas". Obrigatório.
    - variant: ghost (o padrão) | secondary | primary | danger.
    - size: sm (32px visíveis, alvo de 40px por uma área de toque invisível) | md (40px, o padrão) |
      lg (44px).
    - href: renderiza <a>.
    - pressed: true/false vira aria-pressed (botão de alternância). null não emite.
    - expanded: true/false vira aria-expanded (gatilho de menu). null não emite. aria-controls e os
      demais aria-* passam como atributo comum.
    - count: contador visual (ex.: não lidas) no canto; aria-hidden, porque o número precisa estar
      no label. 0 ou null esconde; acima de 99 mostra "99+".
    - disabled: desabilita.

    Ex.: <x-ui.icon-button icon="x-mark" label="Fechar aviso" size="sm" />
         <x-ui.icon-button icon="bell" label="Notificações, 3 não lidas" :count="3" :expanded="false" aria-controls="painel-notificacoes" />
--}}
@props([
    'icon' => null,
    'label' => null,
    'variant' => 'ghost',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'pressed' => null,
    'expanded' => null,
    'count' => null,
    'disabled' => false,
])
@php
    \App\Support\UiProps::required('x-ui.icon-button', 'icon', $icon);
    \App\Support\UiProps::required('x-ui.icon-button', 'label', $label, 'O label vira o aria-label: sem ele o botão não tem nome para leitor de tela.');

    $iconButtonVariant = \App\Support\UiProps::oneOf('x-ui.icon-button', 'variant', $variant, ['ghost', 'secondary', 'primary', 'danger'], 'ghost');
    $iconButtonSize = \App\Support\UiProps::oneOf('x-ui.icon-button', 'size', $size, ['sm', 'md', 'lg'], 'md');
    $iconButtonType = \App\Support\UiProps::oneOf('x-ui.icon-button', 'type', $type, ['button', 'submit', 'reset'], 'button');
    $iconButtonIsLink = filled($href);
    $iconButtonIsDisabled = (bool) $disabled;
    $iconButtonCount = is_numeric($count) ? (int) $count : 0;

    $iconButtonVariantClasses = match ($iconButtonVariant) {
        'ghost' => 'text-muted-foreground hover:bg-surface-muted hover:text-foreground',
        'secondary' => 'border border-border-strong bg-surface text-foreground shadow-xs hover:bg-surface-muted',
        'primary' => 'bg-primary text-primary-foreground shadow-xs hover:bg-primary-hover',
        'danger' => 'text-danger hover:bg-danger-soft',
    };
    $iconButtonSizeClasses = match ($iconButtonSize) {
        'sm' => 'size-8 after:absolute after:-inset-1',
        'md' => 'size-10',
        'lg' => 'size-11',
    };
    $iconButtonIconClass = match ($iconButtonSize) {
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-6',
    };

    $iconButtonAttributes = $attributes
        ->class([
            'relative inline-flex shrink-0 items-center justify-center rounded-control select-none',
            'transition-[color,background-color,border-color,box-shadow,scale] duration-fast ease-smooth-out motion-reduce:transition-none',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
            'motion-safe:active:scale-95',
            'disabled:pointer-events-none disabled:opacity-60 aria-disabled:pointer-events-none aria-disabled:opacity-60',
            $iconButtonSizeClasses,
            $iconButtonVariantClasses,
        ])
        ->merge([
            'data-slot' => 'icon-button',
            'aria-label' => (string) $label,
            'aria-pressed' => is_null($pressed) ? null : ($pressed ? 'true' : 'false'),
            'aria-expanded' => is_null($expanded) ? null : ($expanded ? 'true' : 'false'),
        ]);

    $iconButtonAttributes = $iconButtonIsLink
        ? $iconButtonAttributes->merge([
            'href' => $iconButtonIsDisabled ? null : $href,
            'role' => $iconButtonIsDisabled ? 'link' : null,
            'aria-disabled' => $iconButtonIsDisabled ? 'true' : null,
        ])
        : $iconButtonAttributes->merge([
            'type' => $iconButtonType,
            'disabled' => $iconButtonIsDisabled,
        ]);
@endphp
<{{ $iconButtonIsLink ? 'a' : 'button' }} {{ $iconButtonAttributes }}>
    @if(filled($icon))
        <x-ui.icon :name="$icon" :class="$iconButtonIconClass" />
    @endif
    @if($iconButtonCount > 0)
        <span aria-hidden="true" data-slot="icon-button-count" class="absolute -top-1 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs leading-none font-semibold text-primary-foreground tabular-nums ring-2 ring-surface">{{ $iconButtonCount > 99 ? '99+' : $iconButtonCount }}</span>
    @endif
</{{ $iconButtonIsLink ? 'a' : 'button' }}>@php
    // Sem quebra de linha depois do elemento: o fechamento deste bloco PHP engole a do fim do
    // arquivo, e o componente não ganha espaço sobrando quando fica no meio do texto.
@endphp

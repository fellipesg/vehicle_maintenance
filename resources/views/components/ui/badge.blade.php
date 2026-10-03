{{--
    Rótulo curto de status ou categoria. Estático (não é botão nem link).

    Props:
    - variant: neutral (o padrão: Rascunho, Inativa) | primary (destaque da marca, teal; não é
      sucesso) | success (Ativa, Publicado, Em garantia) | warning (Vence em N dias) | danger |
      info (Agendado, Administrador) | seal | declared.
      seal e declared são o contrato de procedência (.ai/rules/theme.md): tokens prov-*, com o
      .prov-dot correspondente (disco cheio = Selo da oficina; anel tracejado = Declarada) e, sem
      texto no slot, os rótulos padrão "Selo da oficina" e "Declarada". Declarada leva borda
      tracejada.
    - dot: ponto antes do texto nas outras variantes, para o status não depender só da cor.
    - icon: ícone (variante solid) antes do texto, no lugar do ponto.
    - size: sm | md (o padrão) | lg.

    Slot: o texto. Sem emoji.

    Ex.: <x-ui.badge variant="success" dot>Ativa</x-ui.badge>
         <x-ui.badge variant="seal" />
         <x-ui.badge variant="declared">Declarada pelo lojista</x-ui.badge>
         <x-ui.badge variant="info" icon="clock">Agendado</x-ui.badge>
--}}
@props([
    'variant' => 'neutral',
    'dot' => false,
    'icon' => null,
    'size' => 'md',
])
@php
    $badgeVariant = \App\Support\UiProps::oneOf('x-ui.badge', 'variant', $variant, ['neutral', 'primary', 'success', 'warning', 'danger', 'info', 'seal', 'declared'], 'neutral');
    $badgeSize = \App\Support\UiProps::oneOf('x-ui.badge', 'size', $size, ['sm', 'md', 'lg'], 'md');
    $badgeIsProvenance = in_array($badgeVariant, ['seal', 'declared'], true);

    $badgeVariantClasses = match ($badgeVariant) {
        'neutral' => 'border-border-strong bg-surface-muted text-muted-foreground',
        'primary' => 'border-accent-border bg-accent text-accent-foreground',
        'success' => 'border-success/25 bg-success-soft text-success',
        'warning' => 'border-warning/25 bg-warning-soft text-warning',
        'danger' => 'border-danger/25 bg-danger-soft text-danger',
        'info' => 'border-info/25 bg-info-soft text-info',
        'seal' => 'border-prov-verified bg-prov-verified-surface text-prov-verified',
        'declared' => 'border-dashed border-prov-declared bg-prov-declared-surface text-prov-declared',
    };
    $badgeSizeClasses = match ($badgeSize) {
        'sm' => 'gap-1 px-1.5 py-px text-xs',
        'md' => 'gap-1.5 px-2.5 py-0.5 text-xs',
        'lg' => 'gap-1.5 px-3 py-1 text-sm',
    };
    $badgeDefaultLabel = match ($badgeVariant) {
        'seal' => 'Selo da oficina',
        'declared' => 'Declarada',
        default => null,
    };
    $badgeHasText = $slot->hasActualContent();
@endphp
<span {{ $attributes->class([
    'inline-flex w-fit max-w-full shrink-0 items-center rounded-full border font-medium whitespace-nowrap',
    $badgeSizeClasses,
    $badgeVariantClasses,
])->merge(['data-slot' => 'badge', 'data-variant' => $badgeVariant]) }}>
    @if($badgeIsProvenance)
        <span aria-hidden="true" class="prov-dot {{ $badgeVariant === 'seal' ? 'prov-dot--verified' : 'prov-dot--declared' }}"></span>
    @elseif(filled($icon))
        <x-ui.icon :name="$icon" variant="solid" class="size-3.5" />
    @elseif((bool) $dot)
        <span aria-hidden="true" data-slot="badge-dot" class="size-1.5 shrink-0 rounded-full bg-current"></span>
    @endif
    <span class="truncate">@if($badgeHasText){{ $slot }}@else{{ $badgeDefaultLabel }}@endif</span>
</span>@php
    // Sem quebra de linha depois do elemento: o fechamento deste bloco PHP engole a do fim do
    // arquivo, e o componente não ganha espaço sobrando quando fica no meio do texto.
@endphp

{{--
    Um perfil no hub de entrada (/login): o cartão inteiro é o link para a tela de login do portal.

    Props:
    - href, title e description: obrigatórios na prática (destino, nome do perfil e uma frase).
    - icon: nome do x-ui.icon do perfil.
    - highlighted: destaca o perfil sugerido (ex.: Proprietário quando o visitante veio da busca de
      veículo), com a marca "Indicado para você" em texto, não só na cor.

    Vai dentro de <ul role="list">: o próprio componente é o <li>.
--}}
@props([
    'href' => null,
    'title' => null,
    'description' => null,
    'icon' => null,
    'highlighted' => false,
])
<li {{ $attributes->merge(['data-slot' => 'auth-portal-option', 'data-highlighted' => $highlighted ? 'true' : null]) }}>
    <a href="{{ $href }}" @class([
        'group flex min-h-16 items-center gap-4 rounded-card border p-4',
        'transition-[border-color,background-color] duration-fast ease-smooth-out motion-reduce:transition-none',
        'hover:border-accent-border hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
        'border-primary bg-accent' => $highlighted,
        'border-border-strong bg-surface-muted/40' => ! $highlighted,
    ])>
        @if(filled($icon))
            <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-card border border-border-strong bg-surface text-muted-foreground transition-colors duration-fast ease-smooth-out group-hover:text-accent-foreground motion-reduce:transition-none" aria-hidden="true">
                <x-ui.icon :name="$icon" class="size-6" />
            </span>
        @endif
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="font-semibold text-foreground">{{ $title }}</span>
                @if($highlighted)
                    <span class="text-xs font-medium text-accent-foreground">Indicado para você</span>
                @endif
            </span>
            @if(filled($description))
                <span class="mt-0.5 block text-sm text-muted-foreground">{{ $description }}</span>
            @endif
        </span>
        <x-ui.icon name="chevron-right" class="size-5 shrink-0 text-muted-foreground transition-transform duration-base ease-smooth-out motion-reduce:transition-none motion-safe:group-hover:translate-x-0.5" />
    </a>
</li>

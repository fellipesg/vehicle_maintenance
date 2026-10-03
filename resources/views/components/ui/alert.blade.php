{{--
    Aviso dentro da página (erro de carregamento, pendência de cadastro, resultado de ação).

    Props:
    - variant: info (o padrão) | success | warning | danger. Cada uma com ícone próprio (decorativo)
      e um prefixo só para leitor de tela ("Informação:", "Sucesso:", "Atenção:", "Erro:"), para o
      tipo não depender só da cor.
    - title: título curto em negrito (opcional). Também aceita <x-slot:title>.
    - icon: troca o ícone padrão da variante (nome de x-ui.icon).
    - dismissible: mostra o botão "Fechar aviso" (40px de alvo). Some com fade de 150ms pelo
      HSRemoveElement do Preline (já iniciado em resources/js/app.js); só opacidade com
      prefers-reduced-motion. O aviso precisa de id: sem id, um é gerado.

    Papel ARIA: role="alert" em danger e warning (anuncia na hora); role="status" em info e success.
    Para um aviso de erro que já vem na página e não deve interromper, passe role="status".

    Slots: o padrão é a descrição (links dentro dela saem sublinhados) e actions recebe botões
    ("Tentar novamente").

    Ex.: <x-ui.alert variant="danger" title="Não foi possível carregar seus veículos.">
             Verifique a conexão e tente de novo.
             <x-slot:actions><x-ui.button variant="secondary" size="sm" data-retry>Tentar novamente</x-ui.button></x-slot:actions>
         </x-ui.alert>
--}}
@props([
    'variant' => 'info',
    'title' => null,
    'icon' => null,
    'dismissible' => false,
])
@php
    $alertVariant = \App\Support\UiProps::oneOf('x-ui.alert', 'variant', $variant, ['info', 'success', 'warning', 'danger'], 'info');
    $alertIsDismissible = (bool) $dismissible;
    $alertId = $attributes->get('id') ?: 'aviso-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $alertHasTitle = ! \App\Support\UiProps::isBlank($title);
    $alertHasActions = isset($actions) && ! \App\Support\UiProps::isBlank($actions);

    $alertVariantClasses = match ($alertVariant) {
        'info' => 'border-info/25 bg-info-soft text-info',
        'success' => 'border-success/25 bg-success-soft text-success',
        'warning' => 'border-warning/25 bg-warning-soft text-warning',
        'danger' => 'border-danger/25 bg-danger-soft text-danger',
    };
    $alertIcon = filled($icon) ? (string) $icon : match ($alertVariant) {
        'info' => 'information-circle',
        'success' => 'check-circle',
        'warning' => 'exclamation-triangle',
        'danger' => 'exclamation-circle',
    };
    $alertPrefix = match ($alertVariant) {
        'info' => 'Informação:',
        'success' => 'Sucesso:',
        'warning' => 'Atenção:',
        'danger' => 'Erro:',
    };
    $alertRole = in_array($alertVariant, ['danger', 'warning'], true) ? 'alert' : 'status';
@endphp
<div {{ $attributes->class([
    'grid items-start gap-x-3 rounded-card border p-4 text-sm',
    $alertIsDismissible ? 'grid-cols-[auto_minmax(0,1fr)_auto]' : 'grid-cols-[auto_minmax(0,1fr)]',
    'transition-[opacity,translate] duration-fast ease-smooth-out motion-reduce:transition-none hs-removing:opacity-0 motion-safe:hs-removing:-translate-y-1' => $alertIsDismissible,
    $alertVariantClasses,
])->merge([
    'id' => $alertIsDismissible ? $alertId : null,
    'role' => $alertRole,
    'data-slot' => 'alert',
    'data-variant' => $alertVariant,
]) }}>
    <x-ui.icon :name="$alertIcon" variant="solid" class="mt-px size-5" />
    <div class="min-w-0 space-y-1">
        @if($alertHasTitle)
            <p data-slot="alert-title" class="font-semibold"><span class="sr-only">{{ $alertPrefix }} </span>{{ $title }}</p>
        @endif
        @if($slot->hasActualContent())
            <div data-slot="alert-description" class="leading-6 [&_a]:font-medium [&_a]:underline [&_a]:underline-offset-2">@unless($alertHasTitle)<span class="sr-only">{{ $alertPrefix }} </span>@endunless{{ $slot }}</div>
        @elseif(! $alertHasTitle)
            <span class="sr-only">{{ $alertPrefix }}</span>
        @endif
        @if($alertHasActions)
            <div data-slot="alert-actions" class="flex flex-wrap items-center gap-2 pt-2">{{ $actions }}</div>
        @endif
    </div>
    @if($alertIsDismissible)
        <button
            type="button"
            aria-label="Fechar aviso"
            data-slot="alert-dismiss"
            data-alert-dismiss
            data-hs-remove-element="#{{ $alertId }}"
            class="-my-2 -mr-2 inline-flex size-10 shrink-0 items-center justify-center rounded-control opacity-80 transition-[opacity,background-color] duration-fast ease-smooth-out hover:bg-current/10 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
        >
            <x-ui.icon name="x-mark" class="size-5" />
        </button>
    @endif
</div>

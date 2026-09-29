{{--
    Card de perfil da seção "Para quem" da landing (Proprietário, Lojista, Oficina), sobre
    <x-ui.card as="article">: ícone e perfil, título (h3, que rotula o article), descrição, até três
    destaques com check e as ações no rodapé, alinhadas na base do card.

    Props:
    - profile (obrigatório): nome do perfil ("Proprietário").
    - title (obrigatório): título do card ("Dono do carro").
    - icon (obrigatório): ícone de x-ui.icon.
    - points: destaques curtos.

    Slots: o padrão (descrição) e actions (CTA do perfil).
--}}
@props([
    'profile' => null,
    'title' => null,
    'icon' => null,
    'points' => [],
])
@php
    \App\Support\UiProps::required('x-landing.audience', 'profile', $profile);
    \App\Support\UiProps::required('x-landing.audience', 'title', $title);
    \App\Support\UiProps::required('x-landing.audience', 'icon', $icon);
    $audienceSlug = \Illuminate\Support\Str::slug((string) $profile);
    $audienceTitleId = 'publico-'.$audienceSlug.'-titulo';
    $audienceHasActions = isset($actions) && ! \App\Support\UiProps::isBlank($actions);
@endphp
<x-ui.card as="article" padding="lg" :attributes="$attributes->class('h-full')->merge(['aria-labelledby' => $audienceTitleId, 'data-landing-audience' => $audienceSlug])">
    <x-slot:header>
        <div class="flex items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent text-accent-foreground ring-1 ring-accent-border ring-inset">
                <x-ui.icon :name="$icon" class="size-5" />
            </span>
            <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{{ $profile }}</p>
        </div>
        <h3 id="{{ $audienceTitleId }}" class="mt-4 text-2xl font-bold text-foreground">{{ $title }}</h3>
    </x-slot:header>

    <p class="text-sm leading-relaxed text-muted-foreground">{{ $slot }}</p>
    @if ($points !== [])
        <ul role="list" class="mt-5 space-y-2.5 text-sm text-foreground">
            @foreach ($points as $point)
                <li class="flex gap-2">
                    <x-ui.icon name="check" class="mt-px size-4 text-link" />
                    <span>{{ $point }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($audienceHasActions)
        <x-slot:footer class="mt-auto pt-2">
            <div class="flex w-full flex-col items-start gap-3">{{ $actions }}</div>
        </x-slot:footer>
    @endif
</x-ui.card>

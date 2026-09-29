{{--
    Pergunta da FAQ da landing: <details> nativo (abre com teclado e leitor de tela sem JS). O
    chevron gira 180° ao abrir, só com prefers-reduced-motion: no-preference.

    Props:
    - question (obrigatório): a pergunta, no <summary>.

    Slot: a resposta.
--}}
@props([
    'question' => null,
])
@php
    \App\Support\UiProps::required('x-landing.faq-item', 'question', $question);
@endphp
<details {{ $attributes->class(['landing-faq group rounded-card border border-border bg-surface shadow-sm']) }}>
    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 rounded-card px-5 py-4 font-semibold text-foreground">
        <span>{{ $question }}</span>
        <x-ui.icon name="chevron-down" class="size-5 text-link motion-safe:transition-transform motion-safe:duration-base motion-safe:ease-smooth-out group-open:rotate-180" />
    </summary>
    <div class="border-t border-border px-5 pt-3 pb-5 text-sm leading-relaxed text-muted-foreground">
        {{ $slot }}
    </div>
</details>

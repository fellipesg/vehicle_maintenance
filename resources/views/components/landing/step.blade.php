{{--
    Passo de "Como funciona", dentro de <ol class="group/rail ..." data-landing-rail>. O número fica
    num círculo sobre o trilho e um conector liga o círculo ao passo seguinte: vertical no celular,
    horizontal a partir de lg.

    O trilho se desenha uma vez (resources/js/landing.js): abaixo da dobra o JS põe
    data-landing-rail="pending" no <ol>, os conectores encolhem e os círculos apagam; ao entrar na
    tela vira "drawn" e cada passo acende em sequência (300ms entre eles, pelo --landing-step), com o
    conector crescendo até o próximo. Sem JS, com prefers-reduced-motion ou já visível ao carregar,
    o trilho fica desenhado desde o início. Anima só scale e cor.

    Props:
    - number (obrigatório): posição do passo, a partir de 1.
    - title (obrigatório): título do passo (h3).
    - last: último passo, sem conector depois.

    Slot: a descrição do passo.

    Ex.: <x-landing.step :number="1" title="Cadastre o veículo">Placa, chassi e RENAVAM.</x-landing.step>
--}}
@props([
    'number' => null,
    'title' => null,
    'last' => false,
])
@php
    \App\Support\UiProps::required('x-landing.step', 'number', $number);
    \App\Support\UiProps::required('x-landing.step', 'title', $title);
    $stepIndex = max(0, (int) $number - 1);
@endphp
<li {{ $attributes->class(['relative flex gap-4 lg:block', 'pb-8 lg:pb-0' => ! $last])->merge(['style' => '--landing-step: '.$stepIndex, 'data-landing-step' => (int) $number]) }}>
    @unless ($last)
        {{-- Conector até o próximo passo: trilho neutro com o preenchimento teal por cima. --}}
        <span aria-hidden="true" class="absolute top-12 bottom-2 left-5 w-0.5 -translate-x-1/2 rounded-full bg-border lg:top-5 lg:right-[-1rem] lg:bottom-auto lg:left-12 lg:h-0.5 lg:w-auto lg:translate-x-0 lg:-translate-y-1/2">
            <span class="absolute inset-0 origin-top rounded-full bg-primary transition-[scale] duration-slow ease-smooth-out delay-[calc(var(--landing-step)*300ms_+_50ms)] motion-reduce:transition-none group-data-[landing-rail=pending]/rail:scale-y-0 lg:origin-left lg:group-data-[landing-rail=pending]/rail:scale-x-0 lg:group-data-[landing-rail=pending]/rail:scale-y-100"></span>
        </span>
    @endunless
    <span aria-hidden="true" class="relative flex size-10 shrink-0 items-center justify-center rounded-full border-2 border-primary bg-primary text-sm font-bold text-primary-foreground tabular-nums transition-[background-color,border-color,color] duration-base ease-smooth-out delay-[calc(var(--landing-step)*300ms)] motion-reduce:transition-none group-data-[landing-rail=pending]/rail:border-border-strong group-data-[landing-rail=pending]/rail:bg-surface group-data-[landing-rail=pending]/rail:text-muted-foreground">{{ str_pad((string) (int) $number, 2, '0', STR_PAD_LEFT) }}</span>
    <div class="min-w-0 pt-1.5 lg:mt-5 lg:pt-0 lg:pr-4">
        <h3 class="text-lg font-semibold text-foreground">{{ $title }}</h3>
        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ $slot }}</p>
    </div>
</li>

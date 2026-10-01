{{--
    Painel do showcase "Produto" da landing (split-showcase do ObsidianUI, em abas): texto à
    esquerda, divisor pontilhado e um único frame à direita (embaixo no celular), numa moldura de
    raio 32px. Vai no slot padrão de <x-ui.tabs>, como um <x-ui.tab-panel>, e herda dele o
    role="tabpanel", o aria-labelledby e a troca pelo atributo hidden (resources/js/ui/tabs.js).

    A partir de lg a moldura tem altura mínima igual à do painel mais alto (a captura do app), para a
    troca de aba não empurrar o resto da página. Na troca de aba, só com prefers-reduced-motion: no-preference, as duas metades entram em 200ms
    (fade) vindo 12px dos lados opostos, o frame com ease-spring (@starting-style, sem JS). Com
    redução de movimento, a troca é imediata.

    Props:
    - id (obrigatório): id do painel, o mesmo target da <x-ui.tab>.
    - active: painel visível ao carregar.
    - title (obrigatório): título do painel (h3).
    - points: frases curtas da lista de destaques, com ícone de check.

    Slots:
    - frame (obrigatório): o que aparece no frame (captura real do app ou mock com role="img").
    - o padrão: o parágrafo de apoio.
--}}
@props([
    'id' => null,
    'active' => false,
    'title' => null,
    'points' => [],
])
@php
    \App\Support\UiProps::required('x-landing.showcase-panel', 'id', $id);
    \App\Support\UiProps::required('x-landing.showcase-panel', 'title', $title);
@endphp
<x-ui.tab-panel :id="$id" :active="$active" :attributes="$attributes->class('w-full self-stretch')->merge(['data-landing-showcase-panel' => ''])">
    <div class="grid overflow-hidden rounded-[2rem] border border-border bg-background shadow-sm lg:min-h-[40rem] lg:grid-cols-2">
        <div class="flex flex-col justify-center gap-4 p-6 text-left sm:p-10 motion-safe:transition-[opacity,translate] motion-safe:duration-base motion-safe:ease-smooth-out motion-safe:starting:-translate-x-3 motion-safe:starting:opacity-0">
            <h3 class="text-2xl font-bold text-foreground">{{ $title }}</h3>
            <p class="leading-relaxed text-muted-foreground">{{ $slot }}</p>
            @if ($points !== [])
                <ul role="list" class="space-y-2.5 text-sm text-foreground">
                    @foreach ($points as $point)
                        <li class="flex gap-2">
                            <x-ui.icon name="check" class="mt-px size-4 text-link" />
                            <span>{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="flex items-center justify-center border-t-2 border-dotted border-border-strong bg-surface-muted p-6 sm:p-10 lg:border-t-0 lg:border-l-2">
            <div class="w-full motion-safe:transition-[opacity,translate] motion-safe:duration-base motion-safe:ease-spring motion-safe:starting:translate-x-3 motion-safe:starting:opacity-0">
                {{ $frame }}
            </div>
        </div>
    </div>
</x-ui.tab-panel>

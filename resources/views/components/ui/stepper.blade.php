{{--
    Etapas de um fluxo (assistente "Adicionar veículo", procuração e análise, checklist). Lista
    ordenada dentro de um <nav>: a etapa atual leva aria-current="step", as anteriores ficam
    concluídas (check e "concluída" para leitor de tela) e as seguintes, neutras. Cada item diz
    "Etapa N de T" só para leitor de tela; o número visível é decorativo. Renderizado no servidor:
    os fluxos são páginas com POST entre elas, então não há JS.

    Props:
    - steps (obrigatório): as etapas, na ordem. Cada uma é o texto do rótulo ('Documento') ou
      ['label' => 'Capas', 'description' => 'opcional', 'href' => route(...)]. href só vale numa
      etapa concluída: a etapa inteira (número e rótulo) vira link para voltar a ela.
    - current: número da etapa atual, a partir de 1 (o padrão). Acima do total, todas ficam
      concluídas.
    - label: nome do <nav> para leitor de tela (o padrão é 'Etapas').
    - orientation: horizontal (o padrão) | vertical (status em lista, com rótulo e descrição sempre
      visíveis, ex.: "Envio · Análise da equipe · Histórico liberado").

    Na horizontal, no celular os rótulos saem da linha (ficam para leitor de tela): os círculos
    mostram o progresso e uma linha abaixo diz "Etapa N de T · Rótulo", sem estourar a largura.

    Movimento: ao chegar numa etapa, só o trilho que leva à etapa atual se preenche (250ms,
    ease-smooth-out, @starting-style); com prefers-reduced-motion ele já aparece cheio.

    Ex.: <x-ui.stepper label="Etapas para adicionar o veículo" :current="2" :steps="['Documento', 'Conferir', ['label' => 'Capas', 'description' => 'opcional']]" />
         <x-ui.stepper orientation="vertical" label="Andamento da procuração" :current="2" :steps="['Envio', 'Análise da equipe', 'Histórico liberado']" />
--}}
@props([
    'steps' => [],
    'current' => 1,
    'label' => 'Etapas',
    'orientation' => 'horizontal',
])
@php
    $stepperItems = collect(is_iterable($steps) ? $steps : [])
        ->map(function (mixed $step): array {
            $step = is_array($step) ? $step : ['label' => $step];

            return [
                'label' => trim((string) ($step['label'] ?? $step[0] ?? '')),
                'description' => trim((string) ($step['description'] ?? $step[1] ?? '')),
                'href' => filled($step['href'] ?? null) ? (string) $step['href'] : null,
            ];
        })
        ->filter(fn (array $step): bool => $step['label'] !== '')
        ->values();

    \App\Support\UiProps::required('x-ui.stepper', 'steps', $stepperItems->isEmpty() ? null : 'ok', 'Passe ao menos uma etapa com rótulo.');
    \App\Support\UiProps::required('x-ui.stepper', 'label', $label, 'O label é o nome do <nav> para leitor de tela.');

    $stepperOrientation = \App\Support\UiProps::oneOf('x-ui.stepper', 'orientation', $orientation, ['horizontal', 'vertical'], 'horizontal');
    $stepperIsVertical = $stepperOrientation === 'vertical';
    $stepperTotal = $stepperItems->count();
    $stepperCurrent = max(1, (int) $current);
    $stepperCurrentItem = $stepperItems->get($stepperCurrent - 1);
@endphp
<nav {{ $attributes->class(['mb-6'])->merge(['aria-label' => $label, 'data-slot' => 'steps', 'data-orientation' => $stepperOrientation]) }}>
    <ol @class([
        'flex items-start gap-2 sm:gap-3' => ! $stepperIsVertical,
        'grid' => $stepperIsVertical,
    ])>
        @foreach($stepperItems as $stepperIndex => $stepperItem)
            @php
                $stepperNumber = $stepperIndex + 1;
                $stepperState = $stepperNumber < $stepperCurrent ? 'complete' : ($stepperNumber === $stepperCurrent ? 'current' : 'upcoming');
                $stepperIsLast = $stepperNumber === $stepperTotal;
                // Trilho que termina na etapa atual: é o único que se preenche ao chegar nela.
                $stepperLeadsToCurrent = $stepperNumber === $stepperCurrent - 1;
                $stepperHref = $stepperState === 'complete' ? $stepperItem['href'] : null;
                $stepperTag = $stepperHref !== null ? 'a' : 'span';
            @endphp
            <li @class([
                'flex min-w-0 items-start gap-2 sm:gap-3' => ! $stepperIsVertical,
                'flex-1' => ! $stepperIsVertical && ! $stepperIsLast,
                'relative pb-6 last:pb-0' => $stepperIsVertical,
            ]) data-state="{{ $stepperState }}" @if($stepperState === 'current') aria-current="step" @endif>
                <{{ $stepperTag }} @if($stepperHref !== null) href="{{ $stepperHref }}" @endif @class([
                    'group/step flex min-w-0 items-start',
                    'gap-2 sm:gap-3' => ! $stepperIsVertical,
                    'gap-3' => $stepperIsVertical,
                    'rounded-control focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring' => $stepperHref !== null,
                ]) data-slot="steps-item">
                    <span @class([
                        'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold tabular-nums',
                        'bg-primary text-primary-foreground' => $stepperState === 'complete',
                        'border-2 border-ring bg-accent text-accent-foreground' => $stepperState === 'current',
                        'border border-input bg-surface text-muted-foreground' => $stepperState === 'upcoming',
                    ]) aria-hidden="true" data-slot="steps-marker">
                        @if($stepperState === 'complete')
                            <x-ui.icon name="check" variant="solid" class="size-4" />
                        @else
                            {{ $stepperNumber }}
                        @endif
                    </span>
                    <span @class([
                        'min-w-0 pt-1 text-sm',
                        'max-sm:sr-only' => ! $stepperIsVertical,
                        'font-semibold text-foreground' => $stepperState === 'current',
                        'font-medium text-muted-foreground' => $stepperState !== 'current',
                        'underline-offset-2 group-hover/step:text-foreground group-hover/step:underline' => $stepperHref !== null,
                    ]) data-slot="steps-label">
                        <span class="sr-only">Etapa {{ $stepperNumber }} de {{ $stepperTotal }}: </span>
                        <span class="block">{{ $stepperItem['label'] }}@if($stepperState === 'complete')<span class="sr-only"> (concluída)</span>@endif</span>
                        @if($stepperItem['description'] !== '')
                            <span class="block text-xs font-normal text-subtle-foreground">{{ $stepperItem['description'] }}</span>
                        @endif
                    </span>
                </{{ $stepperTag }}>
                @unless($stepperIsLast)
                    <span @class([
                        'overflow-hidden rounded-full bg-border-strong',
                        'relative mt-4 h-0.5 min-w-4 flex-1' => ! $stepperIsVertical,
                        'absolute top-9 bottom-1 left-4 w-0.5 -translate-x-1/2' => $stepperIsVertical,
                    ]) aria-hidden="true" data-slot="steps-connector">
                        @if($stepperState === 'complete')
                            <span @class([
                                'absolute inset-0 rounded-full bg-primary',
                                'origin-left' => ! $stepperIsVertical,
                                'origin-top' => $stepperIsVertical,
                                'transition-[scale] duration-slow ease-smooth-out motion-reduce:transition-none' => $stepperLeadsToCurrent,
                                'starting:scale-x-0' => $stepperLeadsToCurrent && ! $stepperIsVertical,
                                'starting:scale-y-0' => $stepperLeadsToCurrent && $stepperIsVertical,
                            ]) data-slot="{{ $stepperLeadsToCurrent ? 'steps-connector-fill-current' : 'steps-connector-fill' }}"></span>
                        @endif
                    </span>
                @endunless
            </li>
        @endforeach
    </ol>
    @if(! $stepperIsVertical && $stepperCurrentItem !== null)
        <p class="mt-2 text-sm text-muted-foreground sm:hidden" aria-hidden="true" data-slot="steps-summary">Etapa {{ $stepperCurrent }} de {{ $stepperTotal }} · <span class="font-semibold text-foreground">{{ $stepperCurrentItem['label'] }}</span></p>
    @endif
</nav>

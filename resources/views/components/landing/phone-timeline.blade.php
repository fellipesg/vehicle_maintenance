{{--
    Mock da ficha do veículo no hero da landing (tela escura do app), com os dados de
    App\Support\LandingSampleVehicle. Regras de .ai/rules/landing-views.md: Placa atual, Chassi e
    RENAVAM com valores de aparência real; serviços do mais antigo (em cima) para o mais novo (embaixo),
    com a quilometragem subindo; um ponto de procedência por serviço, na mesma ordem. Marcadores
    com glifo (x-landing.provenance-glyph), não letras. Decorativo (aria-hidden): o texto do hero
    diz o mesmo.
--}}
@php
    $timelineEvents = \App\Support\LandingSampleVehicle::events();
    $timelineLastIndex = count($timelineEvents) - 1;
@endphp
<div {{ $attributes->class(['rounded-[1.5rem] border border-automotive-800 bg-automotive-900 p-5']) }} aria-hidden="true" data-landing-timeline-mock>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium tracking-wide text-automotive-300 uppercase">{{ \App\Support\LandingSampleVehicle::MODEL }} · {{ \App\Support\LandingSampleVehicle::YEAR }}</p>
        <span class="shrink-0 rounded-full border border-wrench-400/50 bg-wrench-500/20 px-2.5 py-1 text-xs font-semibold text-wrench-400">{{ \App\Support\LandingSampleVehicle::provenanceSummary() }}</span>
    </div>

    <dl class="mt-4 space-y-2.5">
        <div>
            <dt class="text-xs tracking-wide text-automotive-300 uppercase">Placa atual</dt>
            <dd class="mt-0.5 font-mono text-lg font-semibold text-white">{{ \App\Support\LandingSampleVehicle::PLATE }}</dd>
        </div>
        <div>
            <dt class="text-xs tracking-wide text-automotive-300 uppercase">Chassi</dt>
            <dd class="mt-0.5 font-mono text-xs font-semibold tracking-wider text-white">{{ \App\Support\LandingSampleVehicle::CHASSIS }}</dd>
        </div>
        <div>
            <dt class="text-xs tracking-wide text-automotive-300 uppercase">RENAVAM</dt>
            <dd class="mt-0.5 font-mono text-xs font-semibold tracking-wider text-white">{{ \App\Support\LandingSampleVehicle::RENAVAM }}</dd>
        </div>
    </dl>

    <div class="mt-4 flex items-center gap-1" data-landing-dots>
        @foreach ($timelineEvents as $event)
            <span @class(['prov-dot', 'prov-dot--verified' => $event['sealed'], 'prov-dot--declared' => ! $event['sealed']])></span>
        @endforeach
    </div>

    <ol class="mt-6 space-y-4">
        @foreach ($timelineEvents as $index => $event)
            <li class="flex gap-3">
                <div class="{{ $event['sealed'] ? 'prov-verified' : 'prov-declared' }} flex flex-col items-center">
                    <x-landing.provenance-glyph :sealed="$event['sealed']" />
                    @if ($index !== $timelineLastIndex)
                        <span class="prov-rail {{ $event['sealed'] ? '' : 'prov-rail--declared' }} mt-1 h-full min-h-8"></span>
                    @endif
                </div>
                <div class="min-w-0 flex-1 rounded-xl p-3 {{ $event['sealed'] ? 'border border-wrench-800 bg-automotive-950' : 'border border-dashed border-amber-500/60 bg-amber-950/40' }}">
                    <p class="text-sm font-semibold text-white">{{ $event['title'] }}</p>
                    <p class="mt-0.5 text-xs {{ $event['sealed'] ? 'text-wrench-400' : 'text-amber-200' }}">{{ $event['sealed'] ? 'Selo da oficina · '.$event['author'] : 'Declarada pelo '.$event['author'] }}</p>
                    <p class="mt-1 text-xs text-automotive-300">{{ $event['date'] }} · {{ \App\Support\LandingSampleVehicle::kilometers($event['km']) }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</div>

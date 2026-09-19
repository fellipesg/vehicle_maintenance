@props([
    'compact' => false,
])

@php
    $events = [
        [
            'verified' => true,
            'title' => 'Revisão 40 mil',
            'meta' => 'Selo da oficina · Silva Auto',
            'when' => '18/03/2026 · 40.012 km',
        ],
        [
            'verified' => false,
            'title' => 'Pastilhas dianteiras',
            'meta' => 'Declarada pelo proprietário',
            'when' => '03/06/2026 · 40.580 km',
        ],
        [
            'verified' => true,
            'title' => 'Alinhamento e balanceamento',
            'meta' => 'Selo da oficina · Silva Auto',
            'when' => '22/07/2026 · 41.240 km',
        ],
        [
            'verified' => true,
            'title' => 'Troca de óleo 5W30',
            'meta' => 'Selo da oficina · Silva Auto',
            'when' => '12/08/2026 · 42.180 km',
        ],
    ];
    $lastIndex = count($events) - 1;
@endphp

<div {{ $attributes->class(['rounded-[1.5rem] border border-automotive-800 bg-automotive-900', $compact ? 'p-4' : 'p-5']) }} aria-hidden="true">
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium uppercase tracking-wide text-automotive-400">Honda Civic EX · 2022</p>
        <span class="shrink-0 rounded-full border border-wrench-400/50 bg-wrench-500/20 px-2.5 py-1 text-[11px] font-semibold text-wrench-400">3 com selo · 1 declarada</span>
    </div>

    <dl class="{{ $compact ? 'mt-3 space-y-2' : 'mt-4 space-y-2.5' }}">
        <div>
            <dt class="text-[11px] uppercase tracking-wide text-automotive-500">Placa atual</dt>
            <dd class="mt-0.5 font-mono text-lg font-semibold text-white">ABC1D23</dd>
        </div>
        <div>
            <dt class="text-[11px] uppercase tracking-wide text-automotive-500">Chassi</dt>
            <dd class="mt-0.5 font-mono text-xs font-semibold tracking-wider text-white">93HFB1640NZ004251</dd>
        </div>
        <div>
            <dt class="text-[11px] uppercase tracking-wide text-automotive-500">RENAVAM</dt>
            <dd class="mt-0.5 font-mono text-xs font-semibold tracking-wider text-white">00384719256</dd>
        </div>
    </dl>

    <div class="mt-4 flex items-center gap-1">
        @foreach ($events as $event)
            @if ($event['verified'])
                <span class="prov-dot landing-dot-pulse inline-block h-2.5 w-2.5 rounded-full bg-[#0F766E]" style="animation-delay: {{ $loop->index * 0.35 }}s"></span>
            @else
                <span class="prov-dot landing-dot-pulse inline-block h-2.5 w-2.5 rounded-full border border-dashed border-[#92400E] bg-transparent" style="animation-delay: {{ $loop->index * 0.35 }}s"></span>
            @endif
        @endforeach
    </div>

    <div class="{{ $compact ? 'mt-4 space-y-3' : 'mt-6 space-y-4' }}">
        @foreach ($events as $index => $event)
            <div class="flex gap-3">
                <div class="{{ $event['verified'] ? 'prov-verified' : 'prov-declared' }} flex flex-col items-center">
                    <span class="prov-marker prov-marker--sm {{ $event['verified'] ? 'prov-marker--verified' : 'prov-marker--declared' }}">{{ $event['verified'] ? 'OS' : 'D' }}</span>
                    @if ($index !== $lastIndex)
                        <span class="prov-rail {{ $event['verified'] ? '' : 'prov-rail--declared' }} mt-1 h-full min-h-8"></span>
                    @endif
                </div>
                <div class="min-w-0 flex-1 rounded-xl p-3 {{ $event['verified'] ? 'border border-wrench-800 bg-automotive-950' : 'border border-dashed border-amber-500/60 bg-amber-950/40' }}">
                    <p class="text-sm font-semibold text-white">{{ $event['title'] }}</p>
                    <p class="mt-0.5 text-xs {{ $event['verified'] ? 'text-wrench-400' : 'text-amber-200' }}">{{ $event['meta'] }}</p>
                    <p class="mt-1 text-xs text-automotive-300">{{ $event['when'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>

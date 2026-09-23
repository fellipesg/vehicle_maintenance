{{-- Calendário com o dia da revisão marcado e o selo do carro. --}}
@props(['art'])

<g fill="none" stroke="{{ $art['accent'] }}" stroke-width="5">
    {{-- Argolas --}}
    <line x1="410" y1="92" x2="410" y2="140" stroke-width="10" stroke-linecap="round" />
    <line x1="650" y1="92" x2="650" y2="140" stroke-width="10" stroke-linecap="round" />

    {{-- Corpo do calendário --}}
    <rect x="300" y="118" width="460" height="400" rx="22" fill="{{ $art['to'] }}" fill-opacity="0.75" />
    <path d="M300 140 a22 22 0 0 1 22-22 h416 a22 22 0 0 1 22 22 v62 H300 Z" fill="{{ $art['accent'] }}" fill-opacity="0.85" stroke="none" />
    <line x1="300" y1="202" x2="760" y2="202" />

    {{-- Grade de dias --}}
    @php
        $columns = [340, 442, 544, 646];
        $rows = [240, 330, 420];
        $markedColumn = 544;
        $markedRow = 330;
    @endphp
    @foreach ($rows as $rowY)
        @foreach ($columns as $columnX)
            @continue($columnX === $markedColumn && $rowY === $markedRow)
            <rect x="{{ $columnX }}" y="{{ $rowY }}" width="74" height="62" rx="12" fill="#ffffff" fill-opacity="0.1" stroke="none" />
        @endforeach
    @endforeach

    {{-- Dia da revisão --}}
    <rect x="{{ $markedColumn }}" y="{{ $markedRow }}" width="74" height="62" rx="12" fill="{{ $art['accent'] }}" fill-opacity="0.95" stroke="none" />
    <path d="M{{ $markedColumn + 20 }} {{ $markedRow + 32 }} l12 13 l24 -28" stroke="{{ $art['to'] }}" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
</g>

{{-- Selo do veículo sobre o calendário --}}
<g fill="none" stroke="{{ $art['accent'] }}" stroke-width="5">
    <circle cx="820" cy="424" r="112" fill="{{ $art['from'] }}" />
</g>
<x-blog.art.car :art="$art" transform="translate(738, 360) scale(0.44)" />

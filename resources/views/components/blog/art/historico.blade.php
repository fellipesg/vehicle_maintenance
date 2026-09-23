{{-- Relatório de manutenções ao lado do veículo. --}}
@props(['art'])

<g fill="none" stroke="{{ $art['accent'] }}" stroke-width="5">
    <line x1="170" y1="556" x2="1030" y2="556" stroke="#ffffff" stroke-opacity="0.12" stroke-width="5" stroke-linecap="round" />

    {{-- Folha do relatório --}}
    <rect x="212" y="104" width="286" height="396" rx="18" fill="{{ $art['to'] }}" fill-opacity="0.75" />
    <rect x="248" y="148" width="150" height="18" rx="9" fill="{{ $art['accent'] }}" fill-opacity="0.9" stroke="none" />
    <rect x="248" y="192" width="214" height="10" rx="5" fill="#ffffff" fill-opacity="0.18" stroke="none" />
    <rect x="248" y="220" width="176" height="10" rx="5" fill="#ffffff" fill-opacity="0.18" stroke="none" />

    {{-- Linha do tempo dentro do relatório --}}
    <line x1="268" y1="290" x2="268" y2="430" stroke-opacity="0.55" stroke-width="4" />
    @foreach ([290, 360, 430] as $rowY)
        <circle cx="268" cy="{{ $rowY }}" r="11" fill="{{ $art['accent'] }}" stroke="none" />
        <rect x="298" y="{{ $rowY - 6 }}" width="{{ $loop->last ? 110 : 164 }}" height="12" rx="6" fill="#ffffff" fill-opacity="0.2" stroke="none" />
    @endforeach

    {{-- Selo de conferido sobre a folha --}}
    <circle cx="470" cy="452" r="46" fill="{{ $art['from'] }}" />
    <path d="M450 452 l14 15 l28 -32" stroke-width="9" stroke-linecap="round" stroke-linejoin="round" />
</g>

<x-blog.art.car :art="$art" transform="translate(568, 296)" />

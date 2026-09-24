{{-- Cena genérica: o veículo em movimento. --}}
@props(['art'])

<g fill="none" stroke="{{ $art['accent'] }}" stroke-width="5" stroke-linecap="round">
    <line x1="160" y1="556" x2="1040" y2="556" stroke="#ffffff" stroke-opacity="0.12" />
    @foreach ([[196, 268, 120], [196, 336, 180], [196, 404, 90]] as [$lineX, $lineY, $lineLength])
        <line x1="{{ $lineX }}" y1="{{ $lineY }}" x2="{{ $lineX + $lineLength }}" y2="{{ $lineY }}" stroke-opacity="0.45" stroke-width="10" />
    @endforeach
</g>

<x-blog.art.car :art="$art" transform="translate(378, 230) scale(1.22)" />

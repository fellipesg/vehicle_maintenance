{{-- Documento do veículo com placa e QR Code. --}}
@props(['art'])

<g fill="none" stroke="{{ $art['accent'] }}" stroke-width="5" transform="rotate(-3 600 315)">
    <rect x="368" y="98" width="464" height="430" rx="20" fill="{{ $art['to'] }}" fill-opacity="0.8" />
    <rect x="412" y="142" width="212" height="18" rx="9" fill="{{ $art['accent'] }}" fill-opacity="0.9" stroke="none" />
    <rect x="412" y="186" width="336" height="10" rx="5" fill="#ffffff" fill-opacity="0.18" stroke="none" />
    <rect x="412" y="214" width="268" height="10" rx="5" fill="#ffffff" fill-opacity="0.18" stroke="none" />
    <line x1="412" y1="262" x2="788" y2="262" stroke-opacity="0.35" stroke-width="3" />

    {{-- Placa --}}
    <rect x="412" y="298" width="204" height="86" rx="12" fill="{{ $art['from'] }}" />
    <path d="M412 322 v-12 a12 12 0 0 1 12-12 h180 a12 12 0 0 1 12 12 v12 Z" fill="{{ $art['accent'] }}" fill-opacity="0.75" stroke="none" />
    @foreach ([436, 474, 512, 550] as $charX)
        <rect x="{{ $charX }}" y="338" width="22" height="30" rx="4" fill="#ffffff" fill-opacity="0.35" stroke="none" />
    @endforeach

    {{-- Linhas de dados --}}
    <rect x="412" y="414" width="180" height="10" rx="5" fill="#ffffff" fill-opacity="0.18" stroke="none" />
    <rect x="412" y="442" width="140" height="10" rx="5" fill="#ffffff" fill-opacity="0.18" stroke="none" />

    {{-- QR Code --}}
    <rect x="648" y="298" width="140" height="140" rx="10" fill="{{ $art['from'] }}" stroke-width="4" />
    @foreach ([[668, 318], [740, 318], [668, 390]] as [$markerX, $markerY])
        <rect x="{{ $markerX }}" y="{{ $markerY }}" width="28" height="28" rx="6" stroke-width="6" />
    @endforeach
    @foreach ([[740, 390], [758, 408], [722, 408], [740, 426]] as [$dotX, $dotY])
        <rect x="{{ $dotX }}" y="{{ $dotY }}" width="12" height="12" rx="3" fill="{{ $art['accent'] }}" fill-opacity="0.8" stroke="none" />
    @endforeach
</g>

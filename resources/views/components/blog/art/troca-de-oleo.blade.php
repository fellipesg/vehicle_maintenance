{{-- Motor com a tampa de válvulas aberta e o funil de óleo. --}}
@props(['art'])

<g fill="none" stroke="{{ $art['accent'] }}" stroke-width="5">
    {{-- Tampa de válvulas erguida na dobradiça do bloco --}}
    <g transform="translate(404, 308) rotate(-145)">
        <rect x="0" y="-34" width="272" height="68" rx="16" fill="{{ $art['accent'] }}" fill-opacity="0.28" />
        <line x1="34" y1="0" x2="238" y2="0" stroke-opacity="0.45" stroke-width="4" />
        <circle cx="52" cy="0" r="8" fill="{{ $art['to'] }}" stroke-width="4" />
        <circle cx="220" cy="0" r="8" fill="{{ $art['to'] }}" stroke-width="4" />
    </g>
    <circle cx="404" cy="308" r="11" fill="{{ $art['to'] }}" stroke-width="5" />

    {{-- Bloco do motor --}}
    <rect x="404" y="308" width="392" height="204" rx="20" fill="{{ $art['to'] }}" fill-opacity="0.8" />
    <rect x="430" y="286" width="300" height="34" rx="10" fill="#000000" fill-opacity="0.45" />
    @foreach ([454, 502, 550, 598] as $finX)
        <line x1="{{ $finX }}" y1="358" x2="{{ $finX }}" y2="478" stroke="#ffffff" stroke-opacity="0.16" stroke-width="10" stroke-linecap="round" />
    @endforeach
    <circle cx="742" cy="416" r="44" stroke-width="6" />
    <circle cx="742" cy="416" r="16" fill="{{ $art['accent'] }}" fill-opacity="0.4" stroke-width="4" />

    {{-- Cárter --}}
    <path d="M438 512 h324 l-30 52 h-264 Z" fill="{{ $art['accent'] }}" fill-opacity="0.22" />

    {{-- Funil --}}
    <path d="M492 84 H708 L622 212 V258 H578 V212 Z" fill="{{ $art['accent'] }}" fill-opacity="0.2" stroke-width="6" stroke-linejoin="round" />
    <line x1="492" y1="118" x2="708" y2="118" stroke-opacity="0.6" stroke-width="4" />

    {{-- Óleo caindo no bocal --}}
    <path d="M600 262 c0 0 -15 19 -15 28 a15 15 0 0 0 30 0 c0-9-15-28-15-28z" fill="{{ $art['accent'] }}" fill-opacity="0.85" stroke="none" />
</g>

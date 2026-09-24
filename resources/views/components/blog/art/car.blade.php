{{-- Carro de perfil em caixa local de 372x210, usado por várias cenas. --}}
@props(['art'])

<g {{ $attributes }} fill="none" stroke="{{ $art['accent'] }}" stroke-width="6" stroke-linejoin="round">
    <path
        d="M18 150 C18 124 44 106 96 100 L128 56 C133 48 141 44 150 44 H228 C238 44 247 49 253 57 L286 100 C330 106 352 120 356 146 L357 162 C357 170 351 175 343 175 H32 C24 175 18 169 18 161 Z"
        fill="{{ $art['from'] }}"
        fill-opacity="0.85"
    />
    <polygon points="140,62 186,62 186,98 116,98" fill="{{ $art['accent'] }}" fill-opacity="0.22" stroke-width="4" />
    <polygon points="198,62 240,62 272,98 198,98" fill="{{ $art['accent'] }}" fill-opacity="0.22" stroke-width="4" />
    <line x1="192" y1="102" x2="192" y2="150" stroke-width="4" stroke-opacity="0.7" />
    <line x1="206" y1="126" x2="230" y2="126" stroke-width="5" stroke-linecap="round" />
    <circle cx="100" cy="175" r="34" fill="{{ $art['to'] }}" />
    <circle cx="100" cy="175" r="13" fill="{{ $art['accent'] }}" fill-opacity="0.45" stroke-width="4" />
    <circle cx="284" cy="175" r="34" fill="{{ $art['to'] }}" />
    <circle cx="284" cy="175" r="13" fill="{{ $art['accent'] }}" fill-opacity="0.45" stroke-width="4" />
</g>

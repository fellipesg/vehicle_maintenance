{{-- Selo da oficina com o código público de verificação. --}}
@props(['art'])

<g fill="none" stroke="{{ $art['accent'] }}" stroke-width="5">
    {{-- Fitas --}}
    <path d="M472 388 L430 566 L516 520 Z" fill="{{ $art['accent'] }}" fill-opacity="0.35" stroke-linejoin="round" />
    <path d="M628 388 L670 566 L584 520 Z" fill="{{ $art['accent'] }}" fill-opacity="0.35" stroke-linejoin="round" />

    {{-- Selo --}}
    <circle cx="550" cy="288" r="184" stroke-opacity="0.35" stroke-width="4" stroke-dasharray="16 20" />
    <circle cx="550" cy="288" r="148" fill="{{ $art['to'] }}" fill-opacity="0.85" stroke-width="7" />
    <path d="M484 292 l46 48 l96 -108" stroke-width="18" stroke-linecap="round" stroke-linejoin="round" />

    {{-- Código público --}}
    <rect x="752" y="404" width="296" height="80" rx="16" fill="{{ $art['from'] }}" stroke-width="5" />
    <text
        x="900"
        y="456"
        text-anchor="middle"
        fill="{{ $art['accent'] }}"
        font-size="36"
        font-weight="700"
        letter-spacing="3"
        font-family="Inter, ui-sans-serif, system-ui, sans-serif"
        stroke="none"
    >RVL-7C2D-11</text>
</g>

{{-- Veículo anunciado: etiqueta de preço e a chave que muda de dono. --}}
@props(['art'])

<g fill="none" stroke="{{ $art['accent'] }}" stroke-width="5">
    <line x1="120" y1="536" x2="1080" y2="536" stroke="#ffffff" stroke-opacity="0.12" stroke-width="5" stroke-linecap="round" />

    {{-- Etiqueta --}}
    <path d="M846 132 H1030 a22 22 0 0 1 22 22 V344 a22 22 0 0 1 -22 22 H846 L722 249 Z" fill="{{ $art['to'] }}" fill-opacity="0.85" stroke-width="6" stroke-linejoin="round" />
    <circle cx="884" cy="249" r="18" stroke-width="5" />
    <rect x="930" y="196" width="92" height="14" rx="7" fill="{{ $art['accent'] }}" fill-opacity="0.9" stroke="none" />
    <rect x="930" y="236" width="122" height="14" rx="7" fill="#ffffff" fill-opacity="0.22" stroke="none" />
    <rect x="930" y="276" width="76" height="14" rx="7" fill="#ffffff" fill-opacity="0.22" stroke="none" />

    {{-- Chave entregue --}}
    <g transform="translate(724, 418)">
        <circle cx="34" cy="34" r="30" stroke-width="7" fill="{{ $art['from'] }}" />
        <circle cx="34" cy="34" r="10" fill="{{ $art['accent'] }}" fill-opacity="0.5" stroke="none" />
        <line x1="64" y1="34" x2="196" y2="34" stroke-width="12" stroke-linecap="round" />
        <line x1="150" y1="40" x2="150" y2="70" stroke-width="10" stroke-linecap="round" />
        <line x1="182" y1="40" x2="182" y2="64" stroke-width="10" stroke-linecap="round" />
    </g>
</g>

<x-blog.art.car :art="$art" transform="translate(148, 252) scale(1.06)" />

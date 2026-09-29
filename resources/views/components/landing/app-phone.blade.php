{{--
    Captura real do app Flutter numa moldura de celular (as imagens ficam em public/images/landing,
    publicadas no R2 e lidas por AppStorage::landingUrl(), .ai/rules/views.md). As capturas têm
    proporção 9:20 (1080 × 2400). Carregam sob demanda (loading="lazy"): todas ficam abaixo da dobra.

    Props:
    - src (obrigatório): URL da captura (AppStorage::landingUrl('app-timeline.png')).
    - alt (obrigatório): o que a tela mostra ("App RevisaLog: lista de manutenções").
    - width / height: dimensões da captura, para o navegador reservar o espaço.

    Ex.: <x-landing.app-phone :src="\App\Support\AppStorage::landingUrl('app-vehicle.png')" alt="App RevisaLog: ficha do veículo" />
--}}
@props([
    'src' => null,
    'alt' => null,
    'width' => 1080,
    'height' => 2400,
])
@php
    \App\Support\UiProps::required('x-landing.app-phone', 'src', $src);
    \App\Support\UiProps::required('x-landing.app-phone', 'alt', $alt);
@endphp
<figure {{ $attributes->class([]) }}>
    <div class="overflow-hidden rounded-[2.4rem] border-[10px] border-automotive-950 bg-automotive-950 shadow-2xl ring-1 ring-white/15">
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            class="block h-auto w-full"
            width="{{ (int) $width }}"
            height="{{ (int) $height }}"
            loading="lazy"
            decoding="async"
        >
    </div>
</figure>

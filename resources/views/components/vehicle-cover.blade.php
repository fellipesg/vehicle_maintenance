@props([
    'vehicle',
    'variant' => 'thumb',
    'addCoverUrl' => null,
])

{{--
    Capa nunca é cortada em card e hero (.ai/rules/components.md): a foto aparece inteira com
    object-contain e a sobra da moldura é preenchida pela mesma foto desfocada (decorativa).
    object-cover só na miniatura. A altura vem da própria variante: quem chama não passa aspect-* nem h-*.
    Duas capas (.ai/rules/user-vehicles.md): a paisagem (16:9) a partir de 768px e a retrato (9:16)
    abaixo disso, com <picture>; sem uma delas, a outra vale para os dois tamanhos. É a única
    fonte da capa: nenhuma tela monta a capa em JS.

    Props:
    - vehicle (obrigatório).
    - variant: thumb (o padrão, 56px, object-cover) | card | hero.
    - add-cover-url: sem foto, card e hero mostram o link "Adicionar capa" (só para quem pode editar).
--}}
@php
    $landscapeUrl = $vehicle->cover_photo_url;
    $portraitUrl = $vehicle->cover_photo_portrait_url ?? $landscapeUrl;
    $landscapeUrl = $landscapeUrl ?? $portraitUrl;
    $displayUrl = $portraitUrl ?? $landscapeUrl;
    $hasPhoto = $displayUrl !== null;
    $alt = 'Capa do '.$vehicle->brand.' '.$vehicle->model;
    $isThumb = ! in_array($variant, ['card', 'hero'], true);
    $usesPicture = ! $isThumb && $landscapeUrl !== $portraitUrl;
    $frame = match (true) {
        $variant === 'hero' && $hasPhoto => 'relative w-full overflow-hidden rounded-xl bg-automotive-100 h-64 sm:h-80 lg:h-96',
        $variant === 'hero' => 'relative w-full overflow-hidden rounded-xl bg-automotive-100 h-32 sm:h-40',
        $variant === 'card' => 'relative w-full overflow-hidden bg-automotive-100 h-48 sm:h-52',
        default => 'relative h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-automotive-100',
    };
    $pictureClass = 'absolute inset-0 block h-full w-full';
    $backdropClass = 'absolute inset-0 h-full w-full scale-110 object-cover object-center opacity-60 blur-2xl';
    $imageClass = $isThumb
        ? 'absolute inset-0 h-full w-full object-cover object-center'
        : 'absolute inset-0 h-full w-full object-contain object-center';
@endphp

<div {{ $attributes->merge(['class' => $frame]) }} data-vehicle-cover="{{ $isThumb ? 'thumb' : $variant }}">
    @if ($hasPhoto)
        @if ($isThumb)
            <img src="{{ $displayUrl }}" alt="{{ $alt }}" class="{{ $imageClass }}">
        @elseif ($usesPicture)
            <picture class="{{ $pictureClass }}" aria-hidden="true">
                <source media="(min-width: 768px)" srcset="{{ $landscapeUrl }}">
                <img src="{{ $portraitUrl }}" alt="" class="{{ $backdropClass }}">
            </picture>
            <picture class="{{ $pictureClass }}">
                <source media="(min-width: 768px)" srcset="{{ $landscapeUrl }}">
                <img src="{{ $portraitUrl }}" alt="{{ $alt }}" class="{{ $imageClass }}">
            </picture>
        @else
            <img src="{{ $displayUrl }}" alt="" aria-hidden="true" class="{{ $backdropClass }}">
            <img src="{{ $displayUrl }}" alt="{{ $alt }}" class="{{ $imageClass }}">
        @endif
    @else
        <div class="flex h-full w-full flex-col items-center justify-center gap-1 text-automotive-400">
            <svg class="{{ $isThumb ? 'h-1/2 w-1/2 max-h-10 max-w-10' : 'h-10 w-10' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 16.5h16.5M5.25 16.5l1.2-6.3A1.5 1.5 0 0 1 7.92 9h8.16a1.5 1.5 0 0 1 1.47 1.2l1.2 6.3M7.5 16.5v1.125a1.125 1.125 0 0 1-2.25 0V16.5m13.5 0v1.125a1.125 1.125 0 0 1-2.25 0V16.5M6.75 12h10.5" />
            </svg>
            @unless ($isThumb)
                <span class="text-xs font-medium text-automotive-600">Sem foto de capa</span>
                @if (filled($addCoverUrl))
                    <a href="{{ $addCoverUrl }}" class="link relative z-10 text-xs font-semibold">Adicionar capa</a>
                @endif
            @endunless
        </div>
    @endif
</div>

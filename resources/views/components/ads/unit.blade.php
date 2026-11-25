@props([
    'placement' => null,
])

@php
    /*
     * Bloco de anúncio do AdSense: só aparece no artigo do blog (blog.show) com o cliente e o slot configurados
     * (App\Support\GoogleAdsense). O pedido do anúncio sai de resources/js/ad-consent.js.
     * - placement: nome do bloco em config/services.php (blog_article). Não se chama
     *   "slot" porque $slot é o conteúdo do componente no Blade.
     */
    $adSlotId = \App\Support\GoogleAdsense::isActiveOnCurrentPage() && is_string($placement) ? \App\Support\GoogleAdsense::slot($placement) : null;
@endphp
@if ($adSlotId !== null)
    <aside aria-label="Publicidade" {{ $attributes->class(['min-w-0']) }} data-slot="ad-unit">
        <p class="text-xs text-subtle-foreground">Publicidade</p>
        <ins class="adsbygoogle mt-2 block min-h-24" data-ad-client="{{ \App\Support\GoogleAdsense::client() }}" data-ad-slot="{{ $adSlotId }}" data-ad-format="auto" data-full-width-responsive="true"></ins>
    </aside>
@endif

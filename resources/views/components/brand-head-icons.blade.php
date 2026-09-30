{{--
    Ícones da marca no <head> (favicon e apple-touch-icon) e, com include-og-image, a imagem de
    compartilhamento: og:image e twitter:image uma vez só (WhatsApp e Facebook usam o primeiro
    og:image da página).

    Props:
    - include-og-image: imprime og:image, twitter:card e twitter:image.
    - og-image / og-image-alt: imagem própria da página, no lugar da arte padrão da marca
      (og-preview.png, 1200×630). O layout passa o que a view declarou em @section('og_image') e
      @section('og_image_alt') (a capa do post no blog). O @section já escapa o texto, então aqui
      sai com {!! !!}: com {{ }} o & de uma URL assinada viraria &amp;amp;. Sem tipo nem tamanho,
      que dependem da imagem enviada.

    Ex.: <x-brand-head-icons include-og-image :og-image="$__env->yieldContent('og_image')" :og-image-alt="$__env->yieldContent('og_image_alt')" />
--}}
@props([
    'includeOgImage' => false,
    'ogImage' => null,
    'ogImageAlt' => null,
])
@php
    $brandOgImage = \App\Support\AppStorage::brandUrl('og-preview.png');
    $hasPageOgImage = filled($ogImage);
@endphp

<link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\AppStorage::brandUrl('favicon.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ \App\Support\AppStorage::brandUrl('apple-touch-icon.png') }}">

@if($includeOgImage)
    @if($hasPageOgImage)
        <meta property="og:image" content="{!! $ogImage !!}">
        <meta property="og:image:alt" content="{!! filled($ogImageAlt) ? $ogImageAlt : 'RevisaLog' !!}">
    @else
        <meta property="og:image" content="{{ $brandOgImage }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="RevisaLog">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{!! $hasPageOgImage ? $ogImage : e($brandOgImage) !!}">
@endif

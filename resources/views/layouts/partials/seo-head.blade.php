{{--
    Canonical, Open Graph e o grafo Organization + WebSite. Uma vez só: as views
    não repetem canonical nem og:url. $seoTitle, $seoDescription, $seoCanonical,
    $seoType e $seoNoIndex vêm do layout.
--}}
@if(! empty($seoNoIndex))
    <meta name="robots" content="noindex, follow">
@endif
@if($seoDescription !== '')
    <meta name="description" content="{!! $seoDescription !!}">
@endif
<link rel="canonical" href="{!! $seoCanonical !!}">
<meta property="og:locale" content="pt_BR">
<meta property="og:type" content="{!! $seoType !!}">
<meta property="og:site_name" content="RevisaLog">
<meta property="og:title" content="{!! $seoTitle !!}">
@if($seoDescription !== '')
    <meta property="og:description" content="{!! $seoDescription !!}">
@endif
<meta property="og:url" content="{!! $seoCanonical !!}">
<x-seo.json-ld :data="\App\Support\Seo\StructuredData::siteGraph()" />

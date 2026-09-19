@props([
    'includeOgImage' => false,
])

<link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\AppStorage::brandUrl('favicon.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ \App\Support\AppStorage::brandUrl('apple-touch-icon.png') }}">

@if($includeOgImage)
    <meta property="og:image" content="{{ \App\Support\AppStorage::brandUrl('og-preview.png') }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="RevisaLog">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ \App\Support\AppStorage::brandUrl('og-preview.png') }}">
@endif

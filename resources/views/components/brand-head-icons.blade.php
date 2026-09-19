@props([
    'includeOgImage' => false,
])

<link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\AppStorage::brandUrl('favicon.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ \App\Support\AppStorage::brandUrl('apple-touch-icon.png') }}">

@if($includeOgImage)
    <meta property="og:image" content="{{ \App\Support\AppStorage::brandUrl('og-image.png') }}">
@endif

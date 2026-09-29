@extends('layouts.admin')

@section('title', 'Mapa de oficinas')

@php
    $adminBreadcrumbs = [['Cadastros'], ['Oficinas', route('admin.workshops.index')], ['Mapa de oficinas']];
@endphp

@section('admin_content_wrapper_class', 'flex min-h-[calc(100dvh-3.5rem)] flex-col px-4 py-4 md:px-6')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
    <x-ui.page-header
        title="Mapa de oficinas"
        :description="$onMapCount.' '.($onMapCount === 1 ? 'oficina no mapa' : 'oficinas no mapa').'. É a mesma localização da busca de oficinas por proximidade.'"
    >
        @if($missingCount > 0)
            <x-ui.link :href="$missingUrl" icon="exclamation-triangle" data-admin-map-missing>Ver {{ $missingCount === 1 ? 'a oficina' : 'as '.$missingCount.' oficinas' }} sem coordenadas</x-ui.link>
        @endif
        <x-slot:actions>
            <x-admin.view-switch :list="route('admin.workshops.index')" :map="route('admin.maps.workshops')" current="map" label="Visualização das oficinas" />
        </x-slot:actions>
    </x-ui.page-header>

    @include('admin.maps._map', [
        'mapLabel' => 'Mapa das oficinas',
        'listTitle' => 'Oficinas no mapa',
        'emptyTitle' => 'Nenhuma oficina com coordenadas',
        'emptyDescription' => 'Complete o endereço das oficinas para que elas apareçam no mapa.',
        'tone' => 'workshop',
    ])
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endpush

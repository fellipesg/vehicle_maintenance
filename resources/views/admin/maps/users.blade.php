@extends('layouts.admin')

@section('title', 'Mapa de proprietários')

@php
    $adminBreadcrumbs = [['Cadastros'], ['Usuários', route('admin.users.index')], ['Mapa de proprietários']];
@endphp

@section('admin_content_wrapper_class', 'flex min-h-[calc(100dvh-3.5rem)] flex-col px-4 py-4 md:px-6')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
    <x-ui.page-header
        title="Mapa de proprietários"
        :description="$onMapCount.' '.($onMapCount === 1 ? 'proprietário no mapa' : 'proprietários no mapa').'. Só contas de proprietário com endereço localizado entram aqui.'"
    >
        @if($missingCount > 0)
            <x-ui.link :href="$missingUrl" icon="exclamation-triangle" data-admin-map-missing>Ver {{ $missingCount === 1 ? 'o proprietário' : 'os '.$missingCount.' proprietários' }} sem coordenadas</x-ui.link>
        @endif
        <x-slot:actions>
            <x-admin.view-switch :list="route('admin.users.index')" :map="route('admin.maps.users')" current="map" label="Visualização dos usuários" />
        </x-slot:actions>
    </x-ui.page-header>

    @include('admin.maps._map', [
        'mapLabel' => 'Mapa dos proprietários',
        'listTitle' => 'Proprietários no mapa',
        'emptyTitle' => 'Nenhum proprietário com coordenadas',
        'emptyDescription' => 'Os proprietários aparecem no mapa quando o endereço da conta tem localização.',
        'tone' => 'owner',
    ])
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endpush

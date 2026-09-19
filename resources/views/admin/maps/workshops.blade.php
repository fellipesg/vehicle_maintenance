@extends('layouts.app')

@section('title', 'Mapa de oficinas — Admin')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <h1 class="text-2xl font-bold text-automotive-900">Mapa de oficinas</h1>
    <p class="mt-1 text-sm text-automotive-600">
        <span class="font-semibold">{{ $onMapCount }}</span> no mapa ·
        <span class="font-semibold">{{ $missingCount }}</span> sem coordenadas
    </p>
    <div id="admin-map" class="mt-4 h-[min(70vh,560px)] w-full rounded-xl border border-automotive-200 shadow-inner"></div>
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const pins = @json($pins);
            const map = L.map('admin-map').setView([-14.2, -51.9], 4);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            const bounds = [];
            pins.forEach(function (pin) {
                const marker = L.circleMarker([pin.lat, pin.lng], {
                    radius: 8,
                    fillColor: '#0f766e',
                    color: '#0f766e',
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.85,
                }).addTo(map);
                marker.bindPopup('<strong>' + pin.name + '</strong><br>' + (pin.city || '') + '<br><span class="text-xs">' + (pin.label || '') + '</span>');
                bounds.push([pin.lat, pin.lng]);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
            }
        });
    </script>
@endpush
@endsection

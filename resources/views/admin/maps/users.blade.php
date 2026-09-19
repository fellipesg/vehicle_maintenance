@extends('layouts.admin')

@section('title', 'Mapa de usuários — Admin')
@section('page_heading', 'Mapa de usuários')

@section('admin_main_class', 'flex min-h-0 flex-1 flex-col overflow-hidden')
@section('admin_content_wrapper_class', 'flex h-[calc(100vh-3.5rem)] min-h-0 flex-col gap-3 px-4 py-4 md:px-6')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('content')
    <p class="shrink-0 text-sm text-automotive-600">
        <span class="font-semibold">{{ $onMapCount }}</span> no mapa ·
        <span class="font-semibold">{{ $missingCount }}</span> sem coordenadas
    </p>
    <div id="admin-map" class="min-h-0 flex-1 w-full rounded-xl border border-automotive-200 shadow-inner"></div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const pins = @json($pins);
            const map = L.map('admin-map', { scrollWheelZoom: true }).setView([-14.2, -51.9], 4);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            const bounds = [];
            pins.forEach(function (pin) {
                const marker = L.circleMarker([pin.lat, pin.lng], {
                    radius: 7,
                    fillColor: '#0B1C2C',
                    color: '#0B1C2C',
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.85,
                }).addTo(map);
                marker.bindPopup('<strong>' + pin.name + '</strong><br>' + (pin.city || ''));
                bounds.push([pin.lat, pin.lng]);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
            }

            setTimeout(function () {
                map.invalidateSize();
            }, 100);
        });
    </script>
@endpush

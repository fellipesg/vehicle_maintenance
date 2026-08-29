@extends('layouts.app')

@section('title', 'Veículo')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8" data-api-page="vehicle-show" data-vehicle-id="{{ $vehicleId }}">
    <div data-vehicle-content>
        <div class="card !p-8 text-center text-automotive-500">Carregando veículo...</div>
    </div>
</div>
@endsection

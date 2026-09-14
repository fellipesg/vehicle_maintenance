@extends('layouts.app')

@section('title', 'Editar OS')

@php
    $existingPhotos = $maintenance->photos->groupBy(fn ($photo) => $photo->subject.'_'.$photo->stage);
@endphp

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="mb-6 text-3xl font-bold">Editar Ordem de Serviço</h1>

    <form method="POST" action="{{ route('workshop.maintenances.update', $maintenance) }}" enctype="multipart/form-data" class="card space-y-4">
        @csrf
        @method('PUT')
        @include('partials.workshop-maintenance-form', ['maintenance' => $maintenance, 'vehicle' => $maintenance->vehicle])
        @include('partials.maintenance-items-form', [
            'items' => $maintenance->items->load('warranty'),
            'itemTemplates' => $itemTemplates ?? collect(),
        ])
        @include('partials.maintenance-photos-form', ['existingPhotos' => $existingPhotos, 'editable' => true])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Salvar</button>
            <a href="{{ route('workshop.maintenances.show', $maintenance) }}" class="btn-secondary">Cancelar</a>
        </div>
    </form>

</div>
@endsection

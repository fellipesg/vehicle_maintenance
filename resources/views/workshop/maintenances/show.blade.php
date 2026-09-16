@extends('layouts.app')

@section('title', $maintenance->maintenance_type)

@php
    $categories = [
        'mechanical' => 'Mecânica', 'electrical' => 'Elétrica', 'suspension' => 'Suspensão',
        'painting' => 'Pintura', 'finishing' => 'Acabamento', 'interior' => 'Interior', 'other' => 'Outros',
    ];
    $existingPhotos = $maintenance->photos->groupBy(fn ($photo) => $photo->subject.'_'.$photo->stage);
@endphp

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('workshop.maintenances.index') }}" class="text-sm text-wrench-600 hover:underline">← Voltar</a>
            <h1 class="mt-2 text-3xl font-bold">{{ $maintenance->maintenance_type }}</h1>
            <p class="text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->vehicle->license_plate }}</p>
            @if($maintenance->generalWarranty)
                <p class="mt-2 text-sm">
                    <span class="badge {{ $maintenance->generalWarranty->isVigente() ? 'badge-green' : 'badge-orange' }}">
                        {{ $maintenance->generalWarranty->label() }}
                    </span>
                </p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('workshop.maintenances.edit', $maintenance) }}" class="btn-secondary">Editar</a>
            <form method="POST" action="{{ route('workshop.maintenances.destroy', $maintenance) }}" onsubmit="return confirm('Remover esta OS?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-secondary text-red-600">Excluir</button>
            </form>
        </div>
    </div>

    <x-provenance-seal :maintenance="$maintenance" class="card" />

    <div class="card mt-6">
        <h2 class="mb-4 font-semibold">Detalhes</h2>
        <dl class="grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-automotive-600">Categoria</dt><dd><span class="badge badge-orange">{{ $categories[$maintenance->service_category] ?? '' }}</span></dd></div>
            <div><dt class="text-automotive-600">Data</dt><dd>{{ $maintenance->maintenance_date->format('d/m/Y') }}</dd></div>
            <div><dt class="text-automotive-600">Quilometragem</dt><dd>{{ number_format($maintenance->kilometers, 0, ',', '.') }} km</dd></div>
            <div><dt class="text-automotive-600">Oficina</dt><dd>{{ $maintenance->displayWorkshopName() }}</dd></div>
        </dl>
        @if($maintenance->description)
            <div class="mt-4 border-t border-automotive-100 pt-4">
                <p class="text-sm text-automotive-600">Descrição</p>
                <p class="mt-1 whitespace-pre-wrap">{{ $maintenance->description }}</p>
            </div>
        @endif
    </div>

    <div class="card mt-6">
        <h2 class="mb-4 font-semibold">Itens ({{ $maintenance->items->count() }})</h2>
        @include('partials.maintenance-items-table', ['items' => $maintenance->items])
    </div>

    @if($maintenance->invoices->isNotEmpty())
        <div class="card mt-6">
            <h2 class="mb-4 font-semibold">Notas fiscais ({{ $maintenance->invoices->count() }})</h2>
            <div class="space-y-2">
                @foreach($maintenance->invoices as $invoice)
                    <a href="{{ \App\Support\AppStorage::url($invoice->file_path) }}" target="_blank" rel="noopener"
                       class="flex items-center justify-between rounded-lg bg-automotive-50 p-3 text-sm transition hover:bg-automotive-100">
                        <span class="font-medium">📄 {{ $invoice->file_name }}</span>
                        <span class="text-wrench-600">Abrir →</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @include('partials.maintenance-photos-display', ['maintenance' => $maintenance])
</div>
@endsection

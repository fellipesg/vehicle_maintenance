@extends('layouts.app')

@section('title', $maintenance->maintenance_type)

@php
    $categories = [
        'mechanical' => 'Mecânica', 'electrical' => 'Elétrica', 'suspension' => 'Suspensão',
        'painting' => 'Pintura', 'finishing' => 'Acabamento', 'interior' => 'Interior', 'other' => 'Outros',
    ];
@endphp

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('user.maintenances.index') }}" class="text-sm text-wrench-600 hover:underline">← Voltar</a>
        <h1 class="mt-2 text-3xl font-bold">{{ $maintenance->maintenance_type }}</h1>
        <p class="text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->vehicle->license_plate }}</p>
    </div>

    <div class="card">
        <h2 class="mb-4 font-semibold">Detalhes</h2>
        <dl class="grid gap-2 text-sm sm:grid-cols-2">
            <div class="flex justify-between sm:block"><dt class="text-automotive-600">Categoria</dt><dd><span class="badge badge-orange">{{ $categories[$maintenance->service_category] ?? '' }}</span></dd></div>
            <div class="flex justify-between sm:block"><dt class="text-automotive-600">Data</dt><dd>{{ $maintenance->maintenance_date->format('d/m/Y') }}</dd></div>
            @if($maintenance->kilometers)<div class="flex justify-between sm:block"><dt class="text-automotive-600">Quilometragem</dt><dd>{{ number_format($maintenance->kilometers, 0, ',', '.') }} km</dd></div>@endif
            @if($maintenance->workshop_name)<div class="flex justify-between sm:block"><dt class="text-automotive-600">Oficina</dt><dd>{{ $maintenance->workshop_name }}</dd></div>@endif
            <div class="flex justify-between sm:block"><dt class="text-automotive-600">Revisão obrigatória</dt><dd>{{ $maintenance->is_manufacturer_required ? 'Sim' : 'Não' }}</dd></div>
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
                    <a href="{{ asset('storage/'.$invoice->file_path) }}" target="_blank" rel="noopener"
                       class="flex items-center justify-between rounded-lg bg-automotive-50 p-3 text-sm transition hover:bg-automotive-100">
                        @php $isXml = str_ends_with(strtolower($invoice->file_name), '.xml'); @endphp
                        <span class="font-medium">{{ $isXml ? '🧾' : '📄' }} {{ $invoice->file_name }}</span>
                        <span class="text-wrench-600">Abrir {{ $isXml ? 'XML' : 'PDF' }} →</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Manutenções — Admin')
@section('page_heading', 'Todas as manutenções')

@section('content')
    <div
        data-admin-maintenances
        data-base-url="{{ route('admin.maintenances.index') }}"
        data-verified="{{ $verified === null ? '' : $verified }}"
    >
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <p class="text-sm text-automotive-600">Histórico de manutenções em toda a plataforma.</p>
            <div class="flex flex-wrap gap-2 text-sm" role="group" aria-label="Filtrar por procedência">
                <button
                    type="button"
                    class="btn-secondary admin-maintenance-filter {{ $verified === null ? '!bg-wrench-100' : '' }}"
                    data-maintenance-filter=""
                >
                    Todas
                </button>
                <button
                    type="button"
                    class="btn-secondary admin-maintenance-filter {{ $verified === '1' ? '!bg-wrench-100' : '' }}"
                    data-maintenance-filter="1"
                >
                    Selo da oficina
                </button>
                <button
                    type="button"
                    class="btn-secondary admin-maintenance-filter {{ $verified === '0' ? '!bg-wrench-100' : '' }}"
                    data-maintenance-filter="0"
                >
                    Declaradas
                </button>
            </div>
        </div>

        <x-provenance-legend class="mb-4" />

        <div data-admin-maintenances-results aria-live="polite" aria-busy="false">
            @include('admin.maintenances._results')
        </div>
    </div>
@endsection

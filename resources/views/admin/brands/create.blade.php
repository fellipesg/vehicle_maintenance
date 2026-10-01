@extends('layouts.admin')

@section('title', 'Nova marca')
@section('admin_content_width', 'max-w-3xl')

@php
    $adminBreadcrumbs = [['Catálogo'], ['Marcas e modelos', route('admin.brands.index')], ['Nova marca']];
@endphp

@section('content')
    <x-ui.page-header title="Nova marca" description="Depois de salvar, cadastre os modelos na página da marca." />

    <form method="POST" action="{{ route('admin.brands.store') }}">
        @csrf

        <x-ui.card>
            @include('admin.brands._form', ['brand' => null])

            <x-slot:footer class="justify-end border-t border-border pt-4">
                <x-ui.button variant="secondary" :href="route('admin.brands.index')">Cancelar</x-ui.button>
                <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar marca</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection

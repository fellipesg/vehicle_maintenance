@extends('layouts.admin')

@section('title', 'Editar marca')
@section('admin_content_width', 'max-w-3xl')

@php
    $adminBreadcrumbs = [
        ['Catálogo'],
        ['Marcas e modelos', route('admin.brands.index')],
        [$brand->name, route('admin.brands.show', $brand)],
        ['Editar marca'],
    ];
    $brandModelCount = $brand->models()->count();
    $brandDeletionConsequence = match (true) {
        $brandModelCount === 0 => 'A marca sai do catálogo.',
        $brandModelCount === 1 => 'A marca e o modelo dela saem do catálogo.',
        default => "A marca e os {$brandModelCount} modelos dela saem do catálogo.",
    };
    $brandDeletionMessage = match (true) {
        $brandModelCount === 0 => "A marca {$brand->name} será excluída do catálogo.",
        $brandModelCount === 1 => "A marca {$brand->name} e o modelo dela serão excluídos.",
        default => "A marca {$brand->name} e os {$brandModelCount} modelos dela serão excluídos.",
    };
@endphp

@section('content')
    <x-ui.page-header title="Editar marca" :description="$brand->name" />

    <div class="space-y-10">
        <form method="POST" action="{{ route('admin.brands.update', $brand) }}">
            @csrf
            @method('PUT')

            <x-ui.card>
                @include('admin.brands._form', ['brand' => $brand])

                <x-slot:footer class="justify-end border-t border-border pt-4">
                    <x-ui.button variant="secondary" :href="route('admin.brands.show', $brand)">Cancelar</x-ui.button>
                    <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar alterações</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        <section aria-labelledby="excluir-marca-titulo" class="rounded-card border border-danger/30 bg-danger-soft/40 p-4 sm:p-6">
            <h2 id="excluir-marca-titulo" class="text-base font-semibold text-foreground">Excluir marca</h2>
            <p class="mt-1 text-sm text-muted-foreground">{{ $brandDeletionConsequence }} Veículos já cadastrados não mudam. Não é possível desfazer.</p>

            <form
                method="POST"
                action="{{ route('admin.brands.destroy', $brand) }}"
                class="mt-4"
                data-confirm="{{ $brandDeletionMessage }} Veículos já cadastrados não mudam. Não é possível desfazer."
                data-confirm-title="Excluir a marca {{ $brand->name }}?"
                data-confirm-action-label="Excluir marca"
                data-confirm-variant="danger"
            >
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger" icon="trash">Excluir marca</x-ui.button>
            </form>
        </section>
    </div>
@endsection

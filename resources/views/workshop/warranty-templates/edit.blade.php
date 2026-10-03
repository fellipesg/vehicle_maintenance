@extends('layouts.app')

@section('title', 'Editar modelo de garantia')

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Editar modelo de garantia"
            :description="$template->name"
            :breadcrumbs="[['Modelos de garantia', route('workshop.warranty-templates.index')], [$template->name]]"
        >
            @unless($templatesLocked)
                <x-slot:actions>
                    <x-ui.button variant="ghost" icon="document-duplicate" :href="route('workshop.warranty-templates.create', ['duplicar' => $template->id])">Duplicar</x-ui.button>
                </x-slot:actions>
            @endunless
        </x-ui.page-header>

        <form method="POST" action="{{ route('workshop.warranty-templates.update', $template) }}" class="space-y-6" data-warranty-template-form>
            @csrf
            @method('PUT')

            <x-ui.form-errors />

            @include('workshop.warranty-templates._form', [
                'workshop' => $workshop,
                'template' => $template,
                'templatesLocked' => $templatesLocked,
            ])

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:-mx-6 sm:px-6" data-slot="form-actions">
                <x-ui.button variant="secondary" :href="route('workshop.warranty-templates.index')">Cancelar</x-ui.button>
                <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar</x-ui.button>
            </div>
        </form>
    </x-ui.container>
@endsection

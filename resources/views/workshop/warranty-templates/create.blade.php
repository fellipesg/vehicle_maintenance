@extends('layouts.app')

@section('title', 'Novo modelo de garantia')

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Novo modelo de garantia"
            :description="$source ? 'Cópia de '.$source->name.': ajuste o texto e salve como um modelo novo. O original continua como está.' : 'O modelo aparece ao registrar uma OS, com a validade calculada a partir da data do serviço.'"
            :breadcrumbs="[['Modelos de garantia', route('workshop.warranty-templates.index')], ['Novo modelo']]"
        />

        <form method="POST" action="{{ route('workshop.warranty-templates.store') }}" class="space-y-6" data-warranty-template-form>
            @csrf

            <x-ui.form-errors />

            @include('workshop.warranty-templates._form', [
                'workshop' => $workshop,
                'templatesLocked' => $templatesLocked,
                'source' => $source,
            ])

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:-mx-6 sm:px-6" data-slot="form-actions">
                <x-ui.button variant="secondary" :href="route('workshop.warranty-templates.index')">Cancelar</x-ui.button>
                <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar modelo</x-ui.button>
            </div>
        </form>
    </x-ui.container>
@endsection

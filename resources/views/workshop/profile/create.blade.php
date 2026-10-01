@extends('layouts.app')

@section('title', 'Cadastrar oficina')

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Cadastrar oficina"
            description="Com a oficina cadastrada, cada OS recebe o Selo da oficina e a oficina aparece no diretório para os clientes."
            :breadcrumbs="[['Minha oficina', route('workshop.profile.show')], ['Cadastrar oficina']]"
        />

        <form method="POST" action="{{ route('workshop.profile.store') }}" enctype="multipart/form-data" class="space-y-6" data-workshop-profile-form>
            @csrf

            <x-ui.form-errors />

            @include('workshop.profile._form', ['workshop' => null])

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:-mx-6 sm:px-6" data-slot="form-actions">
                <x-ui.button variant="secondary" :href="route('workshop.dashboard')">Cancelar</x-ui.button>
                <x-ui.button type="submit" icon="check" loading-label="Cadastrando…">Cadastrar oficina</x-ui.button>
            </div>
        </form>
    </x-ui.container>
@endsection

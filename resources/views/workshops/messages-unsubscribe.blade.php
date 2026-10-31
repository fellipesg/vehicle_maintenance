@extends('layouts.app')

@section('title', 'Não receber mais mensagens de oficina')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<x-ui.container size="sm" padded>
    @php
        $workshopLabel = filled($workshopName) ? $workshopName : 'oficina';
    @endphp

    @if($done)
        <x-ui.page-header title="Pronto">
            <x-slot:description>
                @if($scope === \App\Support\Workshop\WorkshopMessageConsent::SCOPE_ALL)
                    Você não vai receber mais mensagens de nenhuma oficina pelo RevisaLog.
                @else
                    Você não vai receber mais mensagens da {{ $workshopLabel }} pelo RevisaLog.
                @endif
            </x-slot:description>
        </x-ui.page-header>
    @else
        <x-ui.page-header title="Não receber mais mensagens de oficina">
            <x-slot:description>
                Confirme para a {{ $workshopLabel }} parar de mandar e-mail e aviso no app sobre os seus veículos.
            </x-slot:description>
        </x-ui.page-header>

        <x-ui.card padding="lg">
            <form method="POST" action="{{ request()->fullUrl() }}">
                @csrf
                <input type="hidden" name="scope" value="{{ \App\Support\Workshop\WorkshopMessageConsent::SCOPE_WORKSHOP }}">
                <x-ui.button type="submit" loading-label="Salvando…">Não quero mais receber desta oficina</x-ui.button>
            </form>

            <form method="POST" action="{{ request()->fullUrl() }}" class="mt-4">
                @csrf
                <input type="hidden" name="scope" value="{{ \App\Support\Workshop\WorkshopMessageConsent::SCOPE_ALL }}">
                <x-ui.button type="submit" variant="secondary" loading-label="Salvando…">Não quero mais receber de nenhuma oficina</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</x-ui.container>
@endsection

@extends('layouts.app')

@section('title', 'Não receber mais mensagens')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<x-ui.container size="sm" padded>
    @if($done)
        <x-ui.page-header title="Pronto">
            <x-slot:description>Você não vai receber mais e-mails do RevisaLog neste endereço.</x-slot:description>
        </x-ui.page-header>
    @else
        <x-ui.page-header title="Não receber mais mensagens">
            <x-slot:description>Confirme para não receber mais e-mails do RevisaLog neste endereço.</x-slot:description>
        </x-ui.page-header>

        <x-ui.card padding="lg">
            <form method="POST" action="{{ request()->fullUrl() }}">
                @csrf
                <x-ui.button type="submit" loading-label="Salvando…">Não quero mais receber</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</x-ui.container>
@endsection

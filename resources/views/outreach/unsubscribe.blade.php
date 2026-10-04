@extends('layouts.app')

@section('title', 'Descadastrar')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<x-ui.container size="sm" padded>
    @if($done)
        <x-ui.page-header title="Descadastrado">
            <x-slot:description>Pronto. Você não vai receber mais mensagens do RevisaLog.</x-slot:description>
        </x-ui.page-header>
    @else
        <x-ui.page-header title="Descadastrar">
            <x-slot:description>Confirme para não receber mais mensagens do RevisaLog neste e-mail.</x-slot:description>
        </x-ui.page-header>

        <x-ui.card padding="lg">
            <form method="POST" action="{{ request()->fullUrl() }}">
                @csrf
                <x-ui.button type="submit" loading-label="Descadastrando…">Não quero mais receber</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</x-ui.container>
@endsection

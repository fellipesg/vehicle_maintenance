@extends('layouts.app')

@section('title', 'Novo modelo de mensagem')

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header title="Novo modelo de mensagem" />

        @include('workshop.message-templates._form', [
            'action' => route('workshop.message-templates.store'),
            'isEdit' => false,
            'template' => null,
        ])
    </x-ui.container>
@endsection

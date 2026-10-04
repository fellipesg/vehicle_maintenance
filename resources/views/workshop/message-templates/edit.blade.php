@extends('layouts.app')

@section('title', 'Editar modelo de mensagem')

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header title="Editar modelo de mensagem" />

        @include('workshop.message-templates._form', [
            'action' => route('workshop.message-templates.update', $template),
            'isEdit' => true,
            'template' => $template,
        ])
    </x-ui.container>
@endsection

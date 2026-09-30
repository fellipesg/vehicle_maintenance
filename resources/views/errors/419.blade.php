@extends('errors.layout')

@php
    // Volta à página do formulário (o referer) quando ela é deste site; senão, ao Início.
    $sessionBackUrl = request()->headers->get('referer');
    $sessionBackUrl = is_string($sessionBackUrl) && parse_url($sessionBackUrl, PHP_URL_HOST) === request()->getHost()
        ? $sessionBackUrl
        : null;
@endphp

@section('title', 'Sua sessão expirou')
@section('icon', 'clock')

@section('message')
    <p>Por segurança, a página fica válida por um tempo. Volte, recarregue a página e envie de novo.</p>
    <p>Se o formulário tinha fotos ou arquivos, será preciso anexá-los outra vez.</p>
@endsection

@if($sessionBackUrl !== null)
    @section('actions')
        <x-ui.button :href="url('/')" variant="secondary" icon="home">Voltar ao site</x-ui.button>
        <x-ui.button :href="$sessionBackUrl" icon="arrow-path">Voltar e recarregar</x-ui.button>
    @endsection
@endif

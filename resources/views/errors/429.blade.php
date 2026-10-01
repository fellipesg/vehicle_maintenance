@extends('errors.layout')

@php
    $retryAfterSeconds = isset($exception) && method_exists($exception, 'getHeaders')
        ? (int) ($exception->getHeaders()['Retry-After'] ?? 0)
        : 0;
    $retryAfterMinutes = max(1, (int) ceil($retryAfterSeconds / 60));
@endphp

@section('title', 'Muitas tentativas seguidas')
@section('icon', 'clock')

@section('message')
    <p>
        Para proteger a sua conta e o site, pausamos os pedidos deste aparelho por um instante.
        @if($retryAfterSeconds > 0)
            Tente de novo em {{ $retryAfterMinutes === 1 ? '1 minuto' : "{$retryAfterMinutes} minutos" }}.
        @else
            Aguarde um minuto e tente de novo.
        @endif
    </p>
@endsection

@extends('errors.layout')

@section('title', 'Esse arquivo expirou')
@section('icon', 'clock')

@section('message')
    <p>
        O PDF do histórico fica disponível por tempo limitado e esse prazo terminou. Abra o veículo
        na sua conta e peça a exportação de novo para baixar um arquivo atualizado.
    </p>
@endsection

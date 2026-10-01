@extends('errors.layout')

@section('title', 'Você não tem acesso a esta página')
@section('icon', 'lock-closed')

@section('message')
    <p>Esta página é de outra pessoa ou de outra conta. Se você chegou aqui por um link, confira se entrou com a conta certa.</p>
@endsection

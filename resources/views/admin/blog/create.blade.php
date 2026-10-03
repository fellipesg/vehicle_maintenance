@extends('layouts.admin')

@section('title', 'Novo artigo')
@section('admin_content_width', 'max-w-6xl')

@php
    $adminBreadcrumbs = [['Conteúdo'], ['Artigos do blog', route('admin.blog.index')], ['Novo artigo']];
@endphp

@section('content')
    <x-ui.page-header title="Novo artigo" description="Salve como rascunho para revisar antes de publicar." />

    <form
        method="POST"
        action="{{ route('admin.blog.store') }}"
        enctype="multipart/form-data"
        data-admin-blog-editor
        data-preview-url="{{ route('admin.blog.preview') }}"
    >
        @csrf
        @include('admin.blog._form', ['post' => null, 'submitLabel' => 'Salvar artigo', 'cancelUrl' => route('admin.blog.index')])
    </form>
@endsection

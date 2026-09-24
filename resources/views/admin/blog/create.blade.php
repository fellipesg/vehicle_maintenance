@extends('layouts.admin')

@section('title', 'Novo post')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.blog.index') }}" class="text-sm text-wrench-600 hover:underline">← Voltar para o blog</a>
        <h1 class="mt-2 text-3xl font-bold">Novo post</h1>
    </div>

    <form method="POST" action="{{ route('admin.blog.store') }}" enctype="multipart/form-data" class="card space-y-4">
        @csrf
        @include('admin.blog._form', ['post' => null])
        <button type="submit" class="btn-primary">Salvar post</button>
    </form>
</div>
@endsection

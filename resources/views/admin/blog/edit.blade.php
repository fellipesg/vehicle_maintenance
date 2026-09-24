@extends('layouts.admin')

@section('title', 'Editar post')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.blog.index') }}" class="text-sm text-wrench-600 hover:underline">← Voltar para o blog</a>
            <h1 class="mt-2 text-3xl font-bold">Editar post</h1>
        </div>
        <a href="{{ route('blog.show', $post) }}" class="btn-secondary" target="_blank" rel="noopener">Ver no site</a>
    </div>

    <form method="POST" action="{{ route('admin.blog.update', $post) }}" enctype="multipart/form-data" class="card space-y-4">
        @csrf
        @method('PUT')
        @include('admin.blog._form')
        <button type="submit" class="btn-primary">Salvar alterações</button>
    </form>

    <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" class="mt-6"
          onsubmit="return confirm('Remover este post? Esta ação não pode ser desfeita.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-danger">Remover post</button>
    </form>
</div>
@endsection

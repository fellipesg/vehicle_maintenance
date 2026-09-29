@extends('layouts.admin')

@section('title', 'Editar artigo')
@section('admin_content_width', 'max-w-6xl')

@php
    $adminBreadcrumbs = [['Conteúdo'], ['Artigos do blog', route('admin.blog.index')], ['Editar artigo']];
    $postIsLive = $post->isPublished();
@endphp

@section('content')
    <x-ui.page-header title="Editar artigo" :description="$post->title">
        <x-slot:actions>
            {{-- Rascunho e agendado abrem a pré-visualização de admin do blog (.ai/rules/blog.md). --}}
            <x-ui.button variant="secondary" icon="arrow-top-right-on-square" :href="route('blog.show', $post)" target="_blank" rel="noopener">{{ $postIsLive ? 'Ver no site' : 'Pré-visualizar no site' }}<span class="sr-only"> (abre em nova aba)</span></x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form
        method="POST"
        action="{{ route('admin.blog.update', $post) }}"
        enctype="multipart/form-data"
        data-admin-blog-editor
        data-preview-url="{{ route('admin.blog.preview') }}"
    >
        @csrf
        @method('PUT')
        @include('admin.blog._form', ['submitLabel' => 'Salvar alterações', 'cancelUrl' => route('admin.blog.index')])
    </form>

    <div class="mt-10 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section aria-labelledby="excluir-artigo-titulo" class="rounded-card border border-danger/30 bg-danger-soft/40 p-4 sm:p-6 lg:col-start-2">
            <h2 id="excluir-artigo-titulo" class="text-base font-semibold text-foreground">Excluir artigo</h2>
            <p class="mt-1 text-sm text-muted-foreground">O artigo e a foto de capa saem do blog. Não é possível desfazer.</p>

            <form
                method="POST"
                action="{{ route('admin.blog.destroy', $post) }}"
                class="mt-4"
                data-confirm="O artigo “{{ $post->title }}” e a foto de capa serão excluídos do blog. Não é possível desfazer."
                data-confirm-title="Excluir o artigo?"
                data-confirm-action-label="Excluir artigo"
                data-confirm-variant="danger"
            >
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger" icon="trash">Excluir artigo</x-ui.button>
            </form>
        </section>
    </div>
@endsection

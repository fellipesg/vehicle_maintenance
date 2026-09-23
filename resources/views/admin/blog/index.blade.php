@extends('layouts.admin')

@section('title', 'Blog')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold">📝 Blog</h1>
            <p class="text-automotive-600">Artigos sobre a plataforma e sobre cuidados com o carro</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.blog.categories.index') }}" class="btn-secondary">Categorias</a>
            <a href="{{ route('admin.blog.create') }}" class="btn-primary">+ Novo post</a>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach(['' => 'Todos', 'published' => 'Publicados', 'draft' => 'Rascunhos'] as $value => $label)
            <a
                href="{{ route('admin.blog.index', $value === '' ? [] : ['status' => $value]) }}"
                @class([
                    'rounded-full border px-3 py-1.5 transition',
                    'border-wrench-500 bg-wrench-500/15 font-semibold text-wrench-700' => $status === $value,
                    'border-automotive-200 bg-white text-automotive-600 hover:border-wrench-400' => $status !== $value,
                ])
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="card overflow-hidden !p-0">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-automotive-200 bg-automotive-50">
                <tr>
                    <th class="px-4 py-3 font-semibold">Título</th>
                    <th class="px-4 py-3 font-semibold">Categoria</th>
                    <th class="px-4 py-3 font-semibold">Publicação</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 text-right font-semibold">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                    <tr class="border-b border-automotive-100 last:border-0">
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $post->title }}</span>
                            <span class="block text-xs text-automotive-500">/blog/{{ $post->slug }}</span>
                        </td>
                        <td class="px-4 py-3 text-automotive-600">{{ $post->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-automotive-600">
                            {{ $post->published_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if($post->isPublished())
                                <span class="badge badge-orange">Publicado</span>
                            @elseif($post->status === \App\Models\BlogPost::STATUS_PUBLISHED)
                                <span class="badge badge-blue">Agendado</span>
                            @else
                                <span class="badge badge-green">Rascunho</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('blog.show', $post) }}" class="text-automotive-600 hover:underline" target="_blank" rel="noopener">Ver</a>
                            <span class="text-automotive-300">·</span>
                            <a href="{{ route('admin.blog.edit', $post) }}" class="text-wrench-600 hover:underline">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-automotive-500">
                            Nenhum post encontrado.
                            <a href="{{ route('admin.blog.create') }}" class="text-wrench-600 hover:underline">Escrever o primeiro</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $posts->links() }}
    </div>
</div>
@endsection

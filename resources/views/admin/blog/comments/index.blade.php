@extends('layouts.admin')

@section('title', 'Comentários do blog')

@php
    use App\Models\BlogComment;

    $adminBreadcrumbs = [['Conteúdo'], ['Comentários do blog']];
    $statusFilters = [
        '' => 'Todos',
        BlogComment::STATUS_PENDING => 'Pendentes',
        BlogComment::STATUS_PUBLISHED => 'Publicados',
        BlogComment::STATUS_HIDDEN => 'Ocultos',
    ];
    $statusFilterOptions = collect($statusFilters)
        ->map(fn (string $statusLabel, string $statusValue): array => [
            'value' => $statusValue,
            'label' => $statusLabel,
            'count' => $statusCounts[$statusValue] ?? 0,
            'href' => route('admin.blog.comments.index', $statusValue !== '' ? ['status' => $statusValue] : []),
        ])
        ->values()
        ->all();
    $emptyTitle = match ($status) {
        BlogComment::STATUS_PENDING => 'Nenhum comentário pendente',
        BlogComment::STATUS_PUBLISHED => 'Nenhum comentário publicado',
        BlogComment::STATUS_HIDDEN => 'Nenhum comentário oculto',
        default => 'Nenhum comentário ainda',
    };
@endphp

@section('content')
    <x-ui.page-header title="Comentários do blog" description="Comentários com link esperam aqui a aprovação antes de aparecer no artigo.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="newspaper" :href="route('admin.blog.index')">Artigos do blog</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4">
        <x-ui.segmented label="Filtrar comentários por situação" :options="$statusFilterOptions" :value="$status" />
    </div>

    <x-ui.table caption="Comentários do blog" stack>
        <x-slot:head>
            <tr>
                <th class="min-w-72">Comentário</th>
                <th>Autor</th>
                <th>Data</th>
                <th>Situação</th>
                <th class="text-right"><span class="sr-only">Ações</span></th>
            </tr>
        </x-slot:head>

        @foreach($comments as $comment)
            <tr data-slot="admin-blog-comment">
                <th scope="row" class="font-normal">
                    @if($comment->post)
                        <x-ui.link :href="route('blog.show', $comment->post)" class="font-medium">{{ $comment->post->title }}</x-ui.link>
                    @endif
                    <p class="mt-1 break-words whitespace-pre-line text-foreground">{{ $comment->body }}</p>
                </th>
                <td class="text-muted-foreground">
                    <span class="block text-foreground">{{ $comment->user?->name }}</span>
                    <span class="block text-xs break-all">{{ $comment->user?->email }}</span>
                </td>
                <td class="whitespace-nowrap text-muted-foreground">
                    <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ \App\Support\DisplayTime::local($comment->created_at)->format('d/m/Y H:i') }}</time>
                </td>
                <td>
                    @if($comment->status === BlogComment::STATUS_PUBLISHED)
                        <x-ui.badge variant="success" dot>Publicado</x-ui.badge>
                    @elseif($comment->status === BlogComment::STATUS_PENDING)
                        <x-ui.badge variant="warning" icon="clock">Pendente</x-ui.badge>
                    @else
                        <x-ui.badge dot>Oculto</x-ui.badge>
                    @endif
                </td>
                <td class="py-2 text-right">
                    <x-admin.row-actions :label="'Ações para o comentário de '.($comment->user?->name ?? 'autor removido')" :id="'comentario-'.$comment->id.'-acoes'">
                        @unless($comment->status === BlogComment::STATUS_PUBLISHED)
                            <x-ui.dropdown-item :action="route('admin.blog.comments.approve', $comment)" icon="check">Aprovar</x-ui.dropdown-item>
                        @endunless
                        @unless($comment->status === BlogComment::STATUS_HIDDEN)
                            <x-ui.dropdown-item :action="route('admin.blog.comments.hide', $comment)" icon="eye-slash">Ocultar</x-ui.dropdown-item>
                        @endunless
                        <x-ui.dropdown-item separator />
                        <x-ui.dropdown-item
                            :action="route('admin.blog.comments.destroy', $comment)"
                            method="DELETE"
                            icon="trash"
                            variant="danger"
                            data-confirm="O comentário será excluído do artigo. Não é possível desfazer."
                            data-confirm-title="Excluir o comentário?"
                            data-confirm-action-label="Excluir comentário"
                            data-confirm-variant="danger"
                        >Excluir</x-ui.dropdown-item>
                    </x-admin.row-actions>
                </td>
            </tr>
        @endforeach

        <x-slot:empty>
            <x-ui.empty-state
                icon="chat-bubble-left-right"
                :title="$emptyTitle"
                :description="$status === '' ? 'Os comentários dos leitores aparecem aqui.' : 'Mostre todos para ver os outros.'"
                variant="plain"
                size="sm"
                heading-level="p"
            >
                @if($status !== '')
                    <x-slot:actions>
                        <x-ui.button variant="secondary" :href="route('admin.blog.comments.index')">Mostrar todos</x-ui.button>
                    </x-slot:actions>
                @endif
            </x-ui.empty-state>
        </x-slot:empty>
    </x-ui.table>

    <div class="mt-4">
        {{ $comments->links() }}
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'Artigos do blog')

@php
    use App\Models\BlogPost;

    $adminBreadcrumbs = [['Conteúdo'], ['Artigos do blog']];
    $hasSearch = $search !== '';
    $keptQuery = fn (array $overrides = []): array => array_filter(array_merge([
        'q' => $hasSearch ? $search : null,
        'status' => $status !== '' ? $status : null,
        'ordenar' => request()->query('ordenar') ? $sort : null,
        'direcao' => request()->query('direcao') ? $direction : null,
    ], $overrides), fn (mixed $value): bool => $value !== null && $value !== '');
    $statusFilters = [
        '' => 'Todos',
        BlogPost::STATUS_PUBLISHED => 'Publicados',
        BlogPost::FILTER_SCHEDULED => 'Agendados',
        BlogPost::STATUS_DRAFT => 'Rascunhos',
    ];
    $statusFilterOptions = collect($statusFilters)
        ->map(fn (string $statusLabel, string $statusValue): array => [
            'value' => $statusValue,
            'label' => $statusLabel,
            'count' => $statusCounts[$statusValue] ?? 0,
            'href' => route('admin.blog.index', $keptQuery(['status' => $statusValue, 'page' => null])),
        ])
        ->values()
        ->all();
    $emptyTitle = match (true) {
        $hasSearch => 'Nenhum artigo para “'.$search.'”',
        $status === BlogPost::STATUS_PUBLISHED => 'Nenhum artigo publicado',
        $status === BlogPost::FILTER_SCHEDULED => 'Nenhum artigo agendado',
        $status === BlogPost::STATUS_DRAFT => 'Nenhum rascunho',
        default => 'Nenhum artigo ainda',
    };
@endphp

@section('content')
    <x-ui.page-header title="Artigos do blog" description="Artigos sobre a plataforma e sobre cuidados com o carro, publicados em /blog.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="tag" :href="route('admin.blog.categories.index')">Categorias do blog</x-ui.button>
            <x-ui.button icon="plus" :href="route('admin.blog.create')">Novo artigo</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <x-ui.segmented label="Filtrar artigos por situação" :options="$statusFilterOptions" :value="$status" />

        <form method="GET" action="{{ route('admin.blog.index') }}" role="search" aria-label="Buscar artigos" class="flex w-full gap-2 lg:w-auto" data-submit-busy="off">
            @foreach($keptQuery(['q' => null, 'page' => null]) as $queryKey => $queryValue)
                <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
            @endforeach
            <label for="admin-blog-search" class="sr-only">Buscar artigo pelo título</label>
            <x-ui.input type="search" id="admin-blog-search" name="q" :value="$search" placeholder="Título do artigo" leading-icon="magnifying-glass" autocomplete="off" class="min-w-0 flex-1 lg:w-72" />
            <x-ui.button type="submit" variant="secondary">Buscar</x-ui.button>
        </form>
    </div>

    @if($hasSearch)
        <p class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground" data-admin-blog-summary>
            <span>{{ $posts->total() }} {{ $posts->total() === 1 ? 'artigo' : 'artigos' }} para <span class="font-medium text-foreground">“{{ $search }}”</span></span>
            <x-ui.link :href="route('admin.blog.index', $keptQuery(['q' => null, 'page' => null]))" icon="x-mark">Limpar busca</x-ui.link>
        </p>
    @endif

    <x-ui.table caption="Artigos do blog" stack :sort="$sort" :direction="$direction">
        <x-slot:head>
            <tr>
                <th class="min-w-64" data-sort="titulo">Título</th>
                <th>Categoria</th>
                <th data-sort="publicacao" data-sort-default="desc">Publicação</th>
                <th>Situação</th>
                <th class="text-right"><span class="sr-only">Ações</span></th>
            </tr>
        </x-slot:head>

        @foreach($posts as $post)
            @php
                $postIsLive = $post->isPublished();
                $postIsScheduled = ! $postIsLive && $post->status === BlogPost::STATUS_PUBLISHED;
            @endphp
            <tr>
                <th scope="row" class="font-medium">
                    <x-ui.link :href="route('admin.blog.edit', $post)">{{ $post->title }}</x-ui.link>
                    <span class="block font-mono text-xs font-normal text-muted-foreground">/blog/{{ $post->slug }}</span>
                </th>
                <td class="text-muted-foreground">{{ $post->category?->name ?? 'Sem categoria' }}</td>
                <td class="whitespace-nowrap text-muted-foreground">
                    @if($postIsScheduled && $post->published_at)
                        <time datetime="{{ $post->published_at->toIso8601String() }}">Agendado para {{ $post->published_at->format('d/m/Y H:i') }}</time>
                    @elseif($post->published_at)
                        <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('d/m/Y H:i') }}</time>
                    @else
                        Não publicado
                    @endif
                </td>
                <td>
                    @if($postIsLive)
                        <x-ui.badge variant="success" dot>Publicado</x-ui.badge>
                    @elseif($postIsScheduled)
                        <x-ui.badge variant="info" icon="clock">Agendado</x-ui.badge>
                    @else
                        <x-ui.badge dot>Rascunho</x-ui.badge>
                    @endif
                </td>
                <td class="py-2 text-right">
                    <x-admin.row-actions :label="'Ações para o artigo '.$post->title" :id="'artigo-'.$post->id.'-acoes'">
                        <x-ui.dropdown-item :href="route('admin.blog.edit', $post)" icon="pencil-square">Editar artigo</x-ui.dropdown-item>
                        <x-ui.dropdown-item :href="route('blog.show', $post)" icon="arrow-top-right-on-square" target="_blank" rel="noopener">{{ $postIsLive ? 'Ver no site' : 'Pré-visualizar' }} <span class="sr-only">(abre em nova aba)</span></x-ui.dropdown-item>
                        <x-ui.dropdown-item separator />
                        <x-ui.dropdown-item
                            :action="route('admin.blog.destroy', $post)"
                            method="DELETE"
                            icon="trash"
                            variant="danger"
                            :data-confirm="'O artigo “'.$post->title.'” e a foto de capa serão excluídos do blog. Não é possível desfazer.'"
                            data-confirm-title="Excluir o artigo?"
                            data-confirm-action-label="Excluir artigo"
                            data-confirm-variant="danger"
                        >Excluir artigo</x-ui.dropdown-item>
                    </x-admin.row-actions>
                </td>
            </tr>
        @endforeach

        <x-slot:empty>
            <x-ui.empty-state
                :icon="$hasSearch ? 'magnifying-glass' : 'newspaper'"
                :title="$emptyTitle"
                :description="$hasSearch ? 'Confira o título e busque de novo.' : ($status === '' ? 'Escreva o primeiro artigo: ele aparece no blog depois de publicado.' : 'Mostre todos os artigos para ver os outros.')"
                variant="plain"
                size="sm"
                heading-level="p"
            >
                <x-slot:actions>
                    @if($status === '' && ! $hasSearch)
                        <x-ui.button icon="plus" :href="route('admin.blog.create')">Novo artigo</x-ui.button>
                    @else
                        <x-ui.button variant="secondary" :href="route('admin.blog.index')">Mostrar todos</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-ui.empty-state>
        </x-slot:empty>
    </x-ui.table>

    <div class="mt-4">
        {{ $posts->links() }}
    </div>
@endsection

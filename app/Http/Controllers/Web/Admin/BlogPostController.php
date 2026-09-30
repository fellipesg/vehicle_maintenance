<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\Blog\BlogCoverService;
use App\Support\BlogCoverArt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function __construct(private readonly BlogCoverService $covers) {}

    /**
     * Modos do bloco "Publicação" do editor (publish_mode): rascunho, publicar agora ou agendar.
     */
    public const PUBLISH_MODES = ['draft', 'now', 'schedule'];

    /**
     * Colunas ordenáveis da lista (?ordenar=) => coluna no SQL.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'titulo' => 'title',
        'publicacao' => 'published_at',
    ];

    /**
     * Filtro da lista por situação: publicados (a data já chegou), agendados (publicados com data
     * futura) e rascunhos, cada um com a contagem; busca pelo título (?q=) e ordenação.
     * Valor desconhecido mostra todos.
     */
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        if (! in_array($status, [BlogPost::STATUS_PUBLISHED, BlogPost::FILTER_SCHEDULED, BlogPost::STATUS_DRAFT], true)) {
            $status = '';
        }

        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $sort = is_string($request->query('ordenar')) && array_key_exists($request->query('ordenar'), self::SORTS)
            ? $request->query('ordenar')
            : 'publicacao';
        $direction = in_array($request->query('direcao'), ['asc', 'desc'], true)
            ? $request->query('direcao')
            : ($sort === 'titulo' ? 'asc' : 'desc');

        $searched = BlogPost::query()->when(
            $search !== '',
            fn ($query) => $query->whereRaw('lower(title) like ?', ['%'.mb_strtolower($search).'%'])
        );

        $statusCounts = [
            '' => (clone $searched)->count(),
            BlogPost::STATUS_PUBLISHED => (clone $searched)->published()->count(),
            BlogPost::FILTER_SCHEDULED => (clone $searched)->scheduled()->count(),
            BlogPost::STATUS_DRAFT => (clone $searched)->where('status', BlogPost::STATUS_DRAFT)->count(),
        ];

        $posts = $searched
            ->with('category')
            ->when($status === BlogPost::STATUS_PUBLISHED, fn ($query) => $query->published())
            ->when($status === BlogPost::FILTER_SCHEDULED, fn ($query) => $query->scheduled())
            ->when($status === BlogPost::STATUS_DRAFT, fn ($query) => $query->where('status', BlogPost::STATUS_DRAFT))
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.blog.index', [
            'posts' => $posts,
            'status' => $status,
            'statusCounts' => $statusCounts,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.create', [
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePost($request);

        $post = BlogPost::create([
            ...$this->attributesFrom($data, $request),
            'slug' => BlogPost::generateSlug($data['slug'] ?? $data['title']),
            'author_id' => $request->user()->id,
        ]);

        if ($request->hasFile('cover')) {
            $this->covers->store($post, $request->file('cover'));
        }

        return redirect()->route('admin.blog.edit', $post)
            ->with('success', 'Artigo criado.');
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.edit', [
            'post' => $post,
            'categories' => $this->categories($post),
        ]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $this->validatePost($request, $post);

        $post->update([
            ...$this->attributesFrom($data, $request, $post),
            'slug' => BlogPost::generateSlug($data['slug'] ?? $data['title'], $post->id),
        ]);

        if ($request->hasFile('cover')) {
            $this->covers->store($post, $request->file('cover'));
        } elseif ($request->boolean('remove_cover')) {
            $this->covers->delete($post->cover_photo_path);
            $post->update(['cover_photo_path' => null]);
        }

        return redirect()->route('admin.blog.edit', $post)
            ->with('success', 'Artigo atualizado.');
    }

    /**
     * Pré-visualização do editor: o Markdown do campo Conteúdo renderizado como no artigo publicado
     * (Str::markdown dentro de .blog-content, .ai/rules/blog.md). Não grava nada.
     */
    public function preview(Request $request): View
    {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:200000'],
        ]);

        return view('admin.blog._preview', ['content' => (string) ($data['content'] ?? '')]);
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->covers->delete($post->cover_photo_path);
        $post->delete();

        return redirect()->route('admin.blog.index')
            ->with('success', 'Artigo excluído.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePost(Request $request, ?BlogPost $post = null): array
    {
        $mode = $request->input('publish_mode');

        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'content' => ['required', 'string'],
            'cover' => ['nullable', 'image', 'max:4096'],
            'cover_photo_alt' => ['nullable', 'string', 'max:160'],
            'cover_art' => ['nullable', 'string', Rule::in(array_keys(BlogCoverArt::SCENES))],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'publish_mode' => ['nullable', Rule::in(self::PUBLISH_MODES)],
            'status' => ['required_without:publish_mode', 'nullable', 'in:'.BlogPost::STATUS_DRAFT.','.BlogPost::STATUS_PUBLISHED],
            'published_at' => $mode === 'schedule'
                ? ['required', 'date', 'after:now']
                : ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFrom(array $data, Request $request, ?BlogPost $post = null): array
    {
        [$status, $publishedAt] = $this->publication($data, $post);

        return [
            'title' => trim($data['title']),
            'blog_category_id' => $data['blog_category_id'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'cover_photo_alt' => $data['cover_photo_alt'] ?? null,
            'cover_art' => $data['cover_art'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'status' => $status,
            'published_at' => $publishedAt,
        ];
    }

    /**
     * Situação e data de publicação a partir do bloco "Publicação" do editor (publish_mode) ou, sem
     * ele, dos campos status e published_at (API antiga do formulário):
     * - draft: rascunho, sem data (.ai/rules/blog.md);
     * - now: publicado; mantém a data de quem já estava no ar e usa agora para os demais;
     * - schedule: publicado com a data futura escolhida (agendado).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: mixed}
     */
    private function publication(array $data, ?BlogPost $post): array
    {
        $mode = $data['publish_mode'] ?? null;

        if ($mode === 'draft') {
            return [BlogPost::STATUS_DRAFT, null];
        }

        if ($mode === 'now') {
            return [BlogPost::STATUS_PUBLISHED, $post?->isPublished() ? $post->published_at : now()];
        }

        if ($mode === 'schedule') {
            return [BlogPost::STATUS_PUBLISHED, $data['published_at']];
        }

        $status = $data['status'];
        $publishedAt = $data['published_at'] ?? null;

        if ($status === BlogPost::STATUS_PUBLISHED && $publishedAt === null) {
            $publishedAt = now();
        }

        if ($status === BlogPost::STATUS_DRAFT) {
            $publishedAt = null;
        }

        return [$status, $publishedAt];
    }

    /**
     * Active categories plus the post's current one, even when it was deactivated,
     * so saving the edit form never drops the category silently.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, BlogCategory>
     */
    private function categories(?BlogPost $post = null): \Illuminate\Database\Eloquent\Collection
    {
        $currentCategoryId = $post?->blog_category_id;

        return BlogCategory::query()
            ->where(fn ($query) => $query
                ->active()
                ->when($currentCategoryId !== null, fn ($query) => $query->orWhere('id', $currentCategoryId)))
            ->orderBy('name')
            ->get();
    }
}

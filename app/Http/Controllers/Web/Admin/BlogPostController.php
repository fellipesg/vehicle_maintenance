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

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $posts = BlogPost::query()
            ->with('category')
            ->when(
                in_array($status, [BlogPost::STATUS_DRAFT, BlogPost::STATUS_PUBLISHED], true),
                fn ($query) => $query->where('status', $status),
            )
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.blog.index', [
            'posts' => $posts,
            'status' => $status,
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
            ->with('success', 'Post criado com sucesso!');
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.edit', [
            'post' => $post,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $this->validatePost($request, $post);

        $post->update([
            ...$this->attributesFrom($data, $request),
            'slug' => BlogPost::generateSlug($data['slug'] ?? $data['title'], $post->id),
        ]);

        if ($request->hasFile('cover')) {
            $this->covers->store($post, $request->file('cover'));
        } elseif ($request->boolean('remove_cover')) {
            $this->covers->delete($post->cover_photo_path);
            $post->update(['cover_photo_path' => null]);
        }

        return redirect()->route('admin.blog.edit', $post)
            ->with('success', 'Post atualizado com sucesso!');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->covers->delete($post->cover_photo_path);
        $post->delete();

        return redirect()->route('admin.blog.index')
            ->with('success', 'Post removido com sucesso!');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePost(Request $request, ?BlogPost $post = null): array
    {
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
            'status' => ['required', 'in:'.BlogPost::STATUS_DRAFT.','.BlogPost::STATUS_PUBLISHED],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFrom(array $data, Request $request): array
    {
        $publishedAt = $data['published_at'] ?? null;

        if ($data['status'] === BlogPost::STATUS_PUBLISHED && $publishedAt === null) {
            $publishedAt = now();
        }

        if ($data['status'] === BlogPost::STATUS_DRAFT) {
            $publishedAt = null;
        }

        return [
            'title' => trim($data['title']),
            'blog_category_id' => $data['blog_category_id'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'cover_photo_alt' => $data['cover_photo_alt'] ?? null,
            'cover_art' => $data['cover_art'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'status' => $data['status'],
            'published_at' => $publishedAt,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BlogCategory>
     */
    private function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return BlogCategory::query()->active()->orderBy('name')->get();
    }
}

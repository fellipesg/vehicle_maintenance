<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\View\View;

class BlogController extends Controller
{
    private const PER_PAGE = 9;

    public function index(): View
    {
        $posts = BlogPost::query()
            ->published()
            ->with('category')
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE);

        return view('blog.index', [
            'posts' => $posts,
            'categories' => $this->categoriesWithPublishedPosts(),
            'category' => null,
        ]);
    }

    public function category(BlogCategory $category): View
    {
        abort_unless($category->is_active, 404);

        $posts = BlogPost::query()
            ->published()
            ->where('blog_category_id', $category->id)
            ->with('category')
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE);

        return view('blog.index', [
            'posts' => $posts,
            'categories' => $this->categoriesWithPublishedPosts(),
            'category' => $category,
        ]);
    }

    public function show(BlogPost $post): View
    {
        if (! $post->isPublished()) {
            abort_unless(auth()->user()?->isAdmin() === true, 404);
        }

        $post->load(['category', 'author']);

        $related = BlogPost::query()
            ->published()
            ->whereKeyNot($post->id)
            ->when(
                $post->blog_category_id !== null,
                fn ($query) => $query->where('blog_category_id', $post->blog_category_id),
            )
            ->with('category')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        $viewer = auth()->user();

        $comments = $post->comments()
            ->with('user')
            ->where(fn ($query) => $query
                ->where('status', BlogComment::STATUS_PUBLISHED)
                ->when($viewer !== null, fn ($query) => $query->orWhere(fn ($own) => $own
                    ->where('status', BlogComment::STATUS_PENDING)
                    ->where('user_id', $viewer->id)))
            )
            ->oldest()
            ->oldest('id')
            ->get();

        $publishedCommentCount = $comments->where('status', BlogComment::STATUS_PUBLISHED)->count();

        return view('blog.show', compact('post', 'related', 'comments', 'publishedCommentCount'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, BlogCategory>
     */
    private function categoriesWithPublishedPosts(): \Illuminate\Support\Collection
    {
        return BlogCategory::query()
            ->active()
            ->whereHas('posts', fn ($query) => $query->published())
            ->withCount(['posts' => fn ($query) => $query->published()])
            ->orderBy('name')
            ->get();
    }
}

<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Moderação dos comentários do blog: lista mais recentes primeiro, com filtro por situação, e as
 * ações aprovar, ocultar e excluir. Regras em .ai/rules/blog.md.
 */
class BlogCommentController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        if (! in_array($status, [BlogComment::STATUS_PUBLISHED, BlogComment::STATUS_PENDING, BlogComment::STATUS_HIDDEN], true)) {
            $status = '';
        }

        $comments = BlogComment::query()
            ->with(['post', 'user'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $counts = BlogComment::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.blog.comments.index', [
            'comments' => $comments,
            'status' => $status,
            'statusCounts' => [
                '' => (int) $counts->sum(),
                BlogComment::STATUS_PENDING => (int) ($counts[BlogComment::STATUS_PENDING] ?? 0),
                BlogComment::STATUS_PUBLISHED => (int) ($counts[BlogComment::STATUS_PUBLISHED] ?? 0),
                BlogComment::STATUS_HIDDEN => (int) ($counts[BlogComment::STATUS_HIDDEN] ?? 0),
            ],
        ]);
    }

    public function approve(Request $request, BlogComment $comment): RedirectResponse
    {
        $this->moderate($request, $comment, BlogComment::STATUS_PUBLISHED);

        return back()->with('success', 'Comentário aprovado e publicado.');
    }

    public function hide(Request $request, BlogComment $comment): RedirectResponse
    {
        $this->moderate($request, $comment, BlogComment::STATUS_HIDDEN);

        return back()->with('success', 'Comentário ocultado.');
    }

    public function destroy(BlogComment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('success', 'Comentário excluído.');
    }

    private function moderate(Request $request, BlogComment $comment, string $status): void
    {
        $comment->forceFill([
            'status' => $status,
            'moderated_by_user_id' => $request->user()->id,
            'moderated_at' => now(),
        ])->save();
    }
}

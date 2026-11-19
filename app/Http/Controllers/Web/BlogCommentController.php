<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreBlogCommentRequest;
use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Comentários dos artigos do blog: só conta logada comenta, um nível, texto puro. Link no texto
 * manda para moderação. Regras em .ai/rules/blog.md.
 */
class BlogCommentController extends Controller
{
    /**
     * Botões "Entre para comentar" e "Criar conta" do artigo: guardam o artigo como destino
     * (url.intended) e levam ao hub de login ou ao cadastro, para a pessoa voltar a ele depois.
     */
    public function enter(Request $request, BlogPost $post, string $destination): RedirectResponse
    {
        abort_unless($post->isPublished(), 404);

        $articleUrl = $this->articleUrl($post);

        if ($request->user() !== null) {
            return redirect()->to($articleUrl);
        }

        $request->session()->put('url.intended', $articleUrl);

        return redirect()->route($destination === 'criar-conta' ? 'register' : 'login');
    }

    public function store(StoreBlogCommentRequest $request, BlogPost $post): RedirectResponse
    {
        abort_unless($post->isPublished(), 404);

        if (! $post->comments_enabled) {
            return redirect()->to($this->articleUrl($post))
                ->with('error', 'Os comentários estão fechados neste artigo.');
        }

        // Armadilha para robôs (campo "website" escondido): finge sucesso e não grava nada.
        if (filled($request->input('website'))) {
            return redirect()->to($this->articleUrl($post))->with('success', 'Comentário publicado.');
        }

        $body = $request->validated('body');
        $needsReview = BlogComment::containsLink($body);

        $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $body,
            'status' => $needsReview ? BlogComment::STATUS_PENDING : BlogComment::STATUS_PUBLISHED,
        ]);

        return redirect()->to($this->articleUrl($post))->with(
            'success',
            $needsReview
                ? 'Recebemos seu comentário. Ele aparece aqui depois de revisado.'
                : 'Comentário publicado.',
        );
    }

    public function destroy(BlogComment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $post = $comment->post;
        $comment->delete();

        return redirect()->to($this->articleUrl($post))->with('success', 'Comentário excluído.');
    }

    private function articleUrl(BlogPost $post): string
    {
        return route('blog.show', $post).'#comentarios';
    }
}

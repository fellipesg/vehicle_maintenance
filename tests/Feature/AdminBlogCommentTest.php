<?php

namespace Tests\Feature;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBlogCommentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->asUser()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_open_or_moderate(): void
    {
        $user = User::factory()->asUser()->create();
        $comment = BlogComment::factory()->pending()->create();

        $this->actingAs($user)->get(route('admin.blog.comments.index'))->assertRedirect(route('user.dashboard'));
        $this->actingAs($user)->post(route('admin.blog.comments.approve', $comment))->assertRedirect(route('user.dashboard'));

        $this->assertTrue($comment->fresh()->isPending());
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.blog.comments.index'))->assertRedirect();
    }

    public function test_list_shows_newest_first_with_author_details_and_brasilia_time(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Artigo dos comentários']);
        $author = User::factory()->asUser()->create(['name' => 'Maria Souza', 'email' => 'maria@example.com']);
        BlogComment::factory()->for($post, 'post')->for($author)->create(['body' => 'Mais antigo', 'created_at' => '2026-11-10 12:00:00']);
        BlogComment::factory()->for($post, 'post')->for($author)->create(['body' => 'Mais novo', 'created_at' => '2026-11-12 15:30:00']);

        $this->actingAs($this->admin())->get(route('admin.blog.comments.index'))
            ->assertOk()
            ->assertSeeInOrder(['Mais novo', 'Mais antigo'])
            ->assertSee('Artigo dos comentários')
            ->assertSee(route('blog.show', $post), false)
            ->assertSee('Maria Souza')
            ->assertSee('maria@example.com')
            ->assertSee('12/11/2026 12:30');
    }

    public function test_filter_chips_narrow_the_list(): void
    {
        BlogComment::factory()->create(['body' => 'corpo-publicado']);
        BlogComment::factory()->pending()->create(['body' => 'corpo-pendente']);
        BlogComment::factory()->hidden()->create(['body' => 'corpo-oculto']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.blog.comments.index'))
            ->assertSee('corpo-publicado')->assertSee('corpo-pendente')->assertSee('corpo-oculto')
            ->assertSee('Todos')->assertSee('Pendentes')->assertSee('Publicados')->assertSee('Ocultos');

        $this->actingAs($admin)->get(route('admin.blog.comments.index', ['status' => 'pending']))
            ->assertSee('corpo-pendente')->assertDontSee('corpo-publicado')->assertDontSee('corpo-oculto');

        $this->actingAs($admin)->get(route('admin.blog.comments.index', ['status' => 'hidden']))
            ->assertSee('corpo-oculto')->assertDontSee('corpo-pendente');

        $this->actingAs($admin)->get(route('admin.blog.comments.index', ['status' => 'published']))
            ->assertSee('corpo-publicado')->assertDontSee('corpo-oculto');
    }

    public function test_approving_publishes_for_everyone_and_records_the_moderator(): void
    {
        $post = BlogPost::factory()->create();
        $comment = BlogComment::factory()->for($post, 'post')->pending()->create(['body' => 'Comentário aprovado depois']);
        $admin = $this->admin();

        $this->get(route('blog.show', $post))->assertDontSee('Comentário aprovado depois');

        $this->actingAs($admin)->from(route('admin.blog.comments.index'))
            ->post(route('admin.blog.comments.approve', $comment))
            ->assertRedirect(route('admin.blog.comments.index'));

        $comment->refresh();
        $this->assertTrue($comment->isPublished());
        $this->assertSame($admin->id, $comment->moderated_by_user_id);
        $this->assertNotNull($comment->moderated_at);

        auth()->logout();
        $this->get(route('blog.show', $post))->assertSee('Comentário aprovado depois');
    }

    public function test_hidden_comment_disappears_and_can_be_approved_again(): void
    {
        $post = BlogPost::factory()->create();
        $comment = BlogComment::factory()->for($post, 'post')->create(['body' => 'Vai sumir']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.comments.hide', $comment));
        $this->assertSame(BlogComment::STATUS_HIDDEN, $comment->fresh()->status);

        auth()->logout();
        $this->get(route('blog.show', $post))->assertDontSee('Vai sumir');

        $this->actingAs($admin)->post(route('admin.blog.comments.approve', $comment));
        $this->assertTrue($comment->fresh()->isPublished());
    }

    public function test_admin_deletes_any_comment(): void
    {
        $comment = BlogComment::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.blog.comments.destroy', $comment))
            ->assertSessionHas('success', 'Comentário excluído.');

        $this->assertDatabaseMissing('blog_comments', ['id' => $comment->id]);
    }

    public function test_admin_row_has_confirm_dialog_data_on_delete(): void
    {
        BlogComment::factory()->create();

        $this->actingAs($this->admin())->get(route('admin.blog.comments.index'))
            ->assertSee('data-confirm-action-label="Excluir comentário"', false);
    }

    public function test_dashboard_lists_pending_comments_only_when_there_are_some(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee('comentário pendente');

        BlogComment::factory()->count(2)->pending()->create();
        BlogComment::factory()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee('2 comentários pendentes')
            ->assertSee(route('admin.blog.comments.index', ['status' => 'pending']), false);
    }

    public function test_admin_nav_links_to_the_comments_page(): void
    {
        $this->actingAs($this->admin())->get(route('admin.blog.index'))
            ->assertSee(route('admin.blog.comments.index'), false)
            ->assertSee('Comentários do blog');
    }

    public function test_post_form_has_the_comments_switch_and_saves_it(): void
    {
        $admin = $this->admin();
        $post = BlogPost::factory()->create();

        $this->actingAs($admin)->get(route('admin.blog.edit', $post))->assertSee('Permitir comentários');

        $payload = ['title' => $post->title, 'content' => 'Texto', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()->subDay()->format('Y-m-d\TH:i')];

        $this->actingAs($admin)->put(route('admin.blog.update', $post), [...$payload, 'comments_enabled' => '0']);
        $this->assertFalse($post->fresh()->comments_enabled);

        $this->actingAs($admin)->put(route('admin.blog.update', $post), [...$payload, 'comments_enabled' => '1']);
        $this->assertTrue($post->fresh()->comments_enabled);

        $this->actingAs($admin)->put(route('admin.blog.update', $post), [...$payload, 'comments_enabled' => '0']);
        $this->actingAs($admin)->put(route('admin.blog.update', $post), $payload);
        $this->assertFalse($post->fresh()->comments_enabled);
    }
}

<?php

namespace Tests\Feature;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\User\DeleteUserAccount;
use App\Support\PortalAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogCommentTest extends TestCase
{
    use RefreshDatabase;

    private function comment(BlogPost $post, User $user, string $body = 'Ótimo artigo, obrigado!'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post(route('blog.comments.store', $post), ['body' => $body]);
    }

    public function test_guest_sees_published_comments_and_the_call_to_action(): void
    {
        $post = BlogPost::factory()->create();
        BlogComment::factory()->for($post, 'post')->create(['body' => 'Comentário visível a todos']);
        BlogComment::factory()->for($post, 'post')->pending()->create(['body' => 'Comentário pendente oculto']);
        BlogComment::factory()->for($post, 'post')->hidden()->create(['body' => 'Comentário oculto pela equipe']);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('id="comentarios"', false)
            ->assertSee('Comentários (1)')
            ->assertSee('Comentário visível a todos')
            ->assertDontSee('Comentário pendente oculto')
            ->assertDontSee('Comentário oculto pela equipe')
            ->assertSee('Entre para comentar')
            ->assertSee(route('blog.comments.enter', [$post, 'entrar']), false)
            ->assertSee(route('blog.comments.enter', [$post, 'criar-conta']), false)
            ->assertDontSee('Publicar comentário');
    }

    public function test_comments_section_comes_after_the_article_content(): void
    {
        $post = BlogPost::factory()->create();

        $html = $this->get(route('blog.show', $post))->getContent();

        $this->assertGreaterThan(strpos($html, 'data-slot="blog-post-content"'), strpos($html, 'id="comentarios"'));
        $this->assertGreaterThan(strpos($html, 'data-slot="blog-post-cta"'), strpos($html, 'id="comentarios"'));
    }

    public function test_guest_cannot_post_and_goes_to_login(): void
    {
        $post = BlogPost::factory()->create();

        $this->from(route('blog.show', $post))
            ->post(route('blog.comments.store', $post), ['body' => 'Olá'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, BlogComment::count());
    }

    public function test_guest_call_to_action_remembers_the_article_for_after_login(): void
    {
        $post = BlogPost::factory()->create();

        $this->get(route('blog.comments.enter', [$post, 'entrar']))->assertRedirect(route('login'));
        $this->assertSame(route('blog.show', $post).'#comentarios', session('url.intended'));

        $this->get(route('blog.comments.enter', [$post, 'criar-conta']))->assertRedirect(route('register'));
    }

    public function test_login_returns_to_the_article_after_the_call_to_action(): void
    {
        $post = BlogPost::factory()->create();
        $user = User::factory()->asUser()->create(['password' => bcrypt('secret-pass-123')]);

        $this->get(route('blog.comments.enter', [$post, 'entrar']));

        $this->post(route('login.submit', 'usuario'), ['email' => $user->email, 'password' => 'secret-pass-123'])
            ->assertRedirect(route('blog.show', $post).'#comentarios');
    }

    public function test_portal_access_allows_the_blog_as_intended_url_for_any_account(): void
    {
        foreach ([User::factory()->asUser()->create(), User::factory()->asGarage()->create(), User::factory()->asWorkshop()->create()] as $user) {
            $this->assertTrue(PortalAccess::allowsIntendedUrl($user, 'http://localhost/blog/um-artigo#comentarios', 'localhost'));
        }

        $this->assertFalse(PortalAccess::allowsIntendedUrl(User::factory()->asUser()->create(), 'http://outro.com/blog/um-artigo', 'localhost'));
    }

    public function test_logged_in_user_comment_is_published_and_visible(): void
    {
        $post = BlogPost::factory()->create();
        $user = User::factory()->asUser()->create();

        $this->comment($post, $user, 'Muito útil, obrigado!')
            ->assertRedirect(route('blog.show', $post).'#comentarios')
            ->assertSessionHas('success', 'Comentário publicado.');

        $this->assertDatabaseHas('blog_comments', ['blog_post_id' => $post->id, 'user_id' => $user->id, 'status' => 'published']);

        $this->get(route('blog.show', $post))->assertSee('Muito útil, obrigado!')->assertSee('Comentários (1)');
    }

    public function test_every_account_type_can_comment(): void
    {
        $post = BlogPost::factory()->create();

        foreach ([User::factory()->asUser(), User::factory()->asGarage(), User::factory()->asWorkshop(), User::factory()->asAdmin()] as $factory) {
            $this->comment($post, $factory->create())->assertSessionHas('success');
        }

        $this->assertSame(4, $post->comments()->published()->count());
    }

    public function test_logged_in_user_sees_the_form(): void
    {
        $post = BlogPost::factory()->create();

        $this->actingAs(User::factory()->asUser()->create())
            ->get(route('blog.show', $post))
            ->assertSee('Seu comentário')
            ->assertSee('Até 2.000 caracteres')
            ->assertSee('Publicar comentário')
            ->assertDontSee('Entre para comentar');
    }

    public function test_comment_with_link_is_pending_and_only_its_author_sees_it_with_a_badge(): void
    {
        $post = BlogPost::factory()->create();
        $author = User::factory()->asUser()->create();

        $this->comment($post, $author, 'Veja meu site em www.exemplo.com.br')
            ->assertSessionHas('success', 'Recebemos seu comentário. Ele aparece aqui depois de revisado.');

        $this->assertDatabaseHas('blog_comments', ['user_id' => $author->id, 'status' => 'pending']);

        $this->actingAs($author)->get(route('blog.show', $post))
            ->assertSee('Veja meu site')
            ->assertSee('Aguardando moderação')
            ->assertSee('Comentários (0)');

        $this->actingAs(User::factory()->asUser()->create())->get(route('blog.show', $post))
            ->assertDontSee('Veja meu site')
            ->assertDontSee('Aguardando moderação');

        auth()->logout();
        $this->get(route('blog.show', $post))->assertDontSee('Veja meu site');
    }

    public function test_link_detection(): void
    {
        foreach (['veja http://a.com', 'https://x.io/abc', 'www.teste', 'visite site.com.br hoje', 'meu.blog'] as $body) {
            $this->assertTrue(BlogComment::containsLink($body), $body);
        }

        foreach (['Troquei o óleo a 10.000 km.', 'Gastei 1.500,00 na revisão', 'Muito bom. Obrigado!', 'versão 2.5 do motor'] as $body) {
            $this->assertFalse(BlogComment::containsLink($body), $body);
        }
    }

    public function test_author_deletes_own_comment_but_not_others(): void
    {
        $post = BlogPost::factory()->create();
        $author = User::factory()->asUser()->create();
        $mine = BlogComment::factory()->for($post, 'post')->for($author)->create();
        $theirs = BlogComment::factory()->for($post, 'post')->create();

        $this->actingAs($author)->delete(route('blog.comments.destroy', $theirs))->assertForbidden();
        $this->assertDatabaseHas('blog_comments', ['id' => $theirs->id]);

        $this->actingAs($author)->delete(route('blog.comments.destroy', $mine))
            ->assertRedirect(route('blog.show', $post).'#comentarios')
            ->assertSessionHas('success', 'Comentário excluído.');
        $this->assertDatabaseMissing('blog_comments', ['id' => $mine->id]);
    }

    public function test_delete_button_with_confirm_only_on_own_comments(): void
    {
        $post = BlogPost::factory()->create();
        $author = User::factory()->asUser()->create();
        BlogComment::factory()->for($post, 'post')->for($author)->create();
        BlogComment::factory()->for($post, 'post')->create();

        $html = $this->actingAs($author)->get(route('blog.show', $post))->getContent();

        $this->assertSame(1, substr_count($html, 'data-confirm-action-label="Excluir comentário"'));
    }

    public function test_guest_cannot_delete(): void
    {
        $comment = BlogComment::factory()->create();

        $this->delete(route('blog.comments.destroy', $comment))->assertRedirect(route('login'));
        $this->assertDatabaseHas('blog_comments', ['id' => $comment->id]);
    }

    public function test_validation_rejects_short_long_and_blank_bodies(): void
    {
        $post = BlogPost::factory()->create();
        $user = User::factory()->asUser()->create();

        foreach (['a', '   ', '', str_repeat('x', 2001)] as $body) {
            $this->comment($post, $user, $body)->assertSessionHasErrors('body');
        }

        $this->assertSame(0, BlogComment::count());

        $this->comment($post, $user, str_repeat('x', 2000))->assertSessionHasNoErrors();
        $this->assertSame(1, BlogComment::count());
    }

    public function test_validation_error_returns_to_the_comments_section(): void
    {
        $post = BlogPost::factory()->create();

        $this->comment($post, User::factory()->asUser()->create(), 'a')
            ->assertRedirect(route('blog.show', $post).'#comentarios');
    }

    public function test_body_is_trimmed(): void
    {
        $post = BlogPost::factory()->create();

        $this->comment($post, User::factory()->asUser()->create(), "   Olá, tudo bem?  \n");

        $this->assertSame('Olá, tudo bem?', BlogComment::first()->body);
    }

    public function test_honeypot_silently_discards_the_comment(): void
    {
        $post = BlogPost::factory()->create();

        $this->actingAs(User::factory()->asUser()->create())
            ->post(route('blog.comments.store', $post), ['body' => 'Compre agora', 'website' => 'http://spam.test'])
            ->assertRedirect(route('blog.show', $post).'#comentarios');

        $this->assertSame(0, BlogComment::count());
    }

    public function test_sixth_comment_in_a_minute_is_throttled(): void
    {
        $post = BlogPost::factory()->create();
        $user = User::factory()->asUser()->create();

        foreach (range(1, 5) as $i) {
            $this->comment($post, $user, "Comentário número {$i}")->assertRedirect();
        }

        $this->comment($post, $user, 'Comentário número 6')->assertStatus(429);
        $this->assertSame(5, BlogComment::count());

        $this->comment($post, User::factory()->asUser()->create(), 'Outra conta comenta')->assertRedirect();
    }

    public function test_daily_cap_is_thirty_per_account(): void
    {
        $post = BlogPost::factory()->create();
        $user = User::factory()->asUser()->create();

        foreach (range(1, 6) as $round) {
            $this->travelTo(now()->addMinutes($round * 2));
            foreach (range(1, 5) as $i) {
                $this->comment($post, $user, "Rodada {$round} comentário {$i}");
            }
        }

        $this->assertSame(30, BlogComment::count());

        $this->travel(2)->minutes();
        $this->comment($post, $user, 'Passou do teto diário')->assertStatus(429);
    }

    public function test_disabled_comments_block_posting_and_show_the_closed_message(): void
    {
        $post = BlogPost::factory()->create(['comments_enabled' => false]);
        $user = User::factory()->asUser()->create();
        BlogComment::factory()->for($post, 'post')->create(['body' => 'Comentário antigo continua']);

        $this->comment($post, $user)->assertSessionHas('error', 'Os comentários estão fechados neste artigo.');
        $this->assertSame(1, BlogComment::count());

        $this->actingAs($user)->get(route('blog.show', $post))
            ->assertSee('Comentário antigo continua')
            ->assertSee('Os comentários estão fechados neste artigo.')
            ->assertDontSee('Publicar comentário');
    }

    public function test_draft_and_scheduled_posts_return_404_on_post(): void
    {
        $user = User::factory()->asUser()->create();

        $this->comment(BlogPost::factory()->draft()->create(), $user)->assertNotFound();
        $this->comment(BlogPost::factory()->scheduled()->create(), $user)->assertNotFound();
        $this->assertSame(0, BlogComment::count());
    }

    public function test_display_name_is_first_name_and_last_initial(): void
    {
        $post = BlogPost::factory()->create();
        $author = User::factory()->asUser()->create(['name' => 'Felipe Silva Gonçalves', 'email' => 'felipe.privado@example.com']);
        BlogComment::factory()->for($post, 'post')->for($author)->create();

        $this->get(route('blog.show', $post))
            ->assertSee('Felipe G.')
            ->assertDontSee('Silva Gonçalves')
            ->assertDontSee('felipe.privado@example.com');

        $this->assertSame('Madonna', BlogComment::displayNameFor('Madonna'));
        $this->assertSame('Ana M.', BlogComment::displayNameFor('  Ana  maria  '));
        $this->assertSame('Leitor', BlogComment::displayNameFor(null));
    }

    public function test_admin_comment_shows_the_team_badge(): void
    {
        $post = BlogPost::factory()->create();
        BlogComment::factory()->for($post, 'post')->for(User::factory()->asAdmin()->create(['name' => 'Equipe Base']))->create();
        BlogComment::factory()->for($post, 'post')->for(User::factory()->asUser()->create())->create();

        $this->assertSame(1, substr_count($this->get(route('blog.show', $post))->getContent(), 'Equipe RevisaLog'));
    }

    public function test_body_is_escaped_and_keeps_line_breaks(): void
    {
        $post = BlogPost::factory()->create();
        BlogComment::factory()->for($post, 'post')->create(['body' => "<script>alert('x')</script>\n**negrito**"]);

        $this->get(route('blog.show', $post))
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('&lt;script&gt;alert(', false)
            ->assertSee('**negrito**', false)
            ->assertSee('whitespace-pre-line', false);
    }

    public function test_json_ld_has_the_published_comment_count(): void
    {
        $post = BlogPost::factory()->create();
        BlogComment::factory()->count(2)->for($post, 'post')->create();
        BlogComment::factory()->for($post, 'post')->pending()->create();

        $this->get(route('blog.show', $post))->assertSee('"commentCount":2', false);
    }

    public function test_comments_are_not_in_the_feed_nor_the_sitemap(): void
    {
        $post = BlogPost::factory()->create();
        BlogComment::factory()->for($post, 'post')->create(['body' => 'Texto exclusivo do comentário']);

        $this->get(route('blog.feed'))->assertDontSee('Texto exclusivo do comentário');
        $this->get('/sitemap.xml')->assertDontSee('Texto exclusivo do comentário');
    }

    public function test_account_deletion_removes_the_persons_comments(): void
    {
        $post = BlogPost::factory()->create();
        $leaving = User::factory()->asUser()->create();
        $staying = User::factory()->asUser()->create();
        BlogComment::factory()->count(2)->for($post, 'post')->for($leaving)->create();
        BlogComment::factory()->for($post, 'post')->for($leaving)->pending()->create();
        $kept = BlogComment::factory()->for($post, 'post')->for($staying)->create();

        app(DeleteUserAccount::class)->handle($leaving);

        $this->assertSame(0, BlogComment::where('user_id', $leaving->id)->count());
        $this->assertDatabaseHas('blog_comments', ['id' => $kept->id]);
    }

    public function test_comments_enabled_defaults_to_true_and_casts_to_boolean(): void
    {
        $post = BlogPost::factory()->create();

        $this->assertTrue($post->fresh()->comments_enabled);
    }
}

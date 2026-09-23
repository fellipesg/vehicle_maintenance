<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_published_posts_only(): void
    {
        $published = BlogPost::factory()->create(['title' => 'Troca de óleo por quilometragem']);
        $draft = BlogPost::factory()->draft()->create(['title' => 'Rascunho interno']);
        $scheduled = BlogPost::factory()->scheduled()->create(['title' => 'Post agendado']);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title)
            ->assertDontSee($scheduled->title);
    }

    public function test_category_page_lists_only_its_published_posts(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Manutenção', 'slug' => 'manutencao']);
        $inCategory = BlogPost::factory()->for($category, 'category')->create(['title' => 'Revisão dos 40 mil km']);
        $other = BlogPost::factory()->create(['title' => 'Como funciona o selo']);

        $this->get(route('blog.category', $category))
            ->assertOk()
            ->assertSee($inCategory->title)
            ->assertDontSee($other->title);
    }

    public function test_inactive_category_page_returns_not_found(): void
    {
        $category = BlogCategory::factory()->inactive()->create();

        $this->get(route('blog.category', $category))->assertNotFound();
    }

    public function test_show_renders_markdown_content(): void
    {
        $post = BlogPost::factory()->create([
            'title' => 'Histórico vinculado ao veículo',
            'content' => "## Por que o chassi\n\nA placa muda, o chassi não.",
        ]);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('<h2>Por que o chassi</h2>', false)
            ->assertSee('A placa muda, o chassi não.');
    }

    public function test_guest_cannot_open_draft_post(): void
    {
        $post = BlogPost::factory()->draft()->create();

        $this->get(route('blog.show', $post))->assertNotFound();
    }

    public function test_admin_can_preview_draft_post(): void
    {
        $admin = User::factory()->asUser()->create(['is_admin' => true]);
        $post = BlogPost::factory()->draft()->create(['title' => 'Rascunho em revisão']);

        $this->actingAs($admin)
            ->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('Rascunho em revisão')
            ->assertSee('ainda não está publicado');
    }

    public function test_feed_returns_published_posts_as_rss(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Revisão programada']);
        BlogPost::factory()->draft()->create(['title' => 'Rascunho do feed']);

        $response = $this->get(route('blog.feed'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('<rss version="2.0"', false)
            ->assertSee($post->title)
            ->assertDontSee('Rascunho do feed');
    }

    public function test_sitemap_lists_published_posts(): void
    {
        $post = BlogPost::factory()->create();
        BlogPost::factory()->draft()->create(['slug' => 'rascunho-sitemap']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('blog.show', $post), false)
            ->assertDontSee('rascunho-sitemap');
    }

    public function test_footer_links_to_the_blog(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('blog.index'), false);
    }

    public function test_post_without_cover_photo_gets_generated_art(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Manutenção', 'slug' => 'manutencao']);
        $post = BlogPost::factory()->for($category, 'category')->create(['cover_photo_path' => null]);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('aria-label="Ilustração de Manutenção"', false)
            ->assertSee('<svg', false);
    }

    public function test_post_scene_overrides_the_category_illustration(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Manutenção', 'slug' => 'manutencao']);
        $post = BlogPost::factory()->for($category, 'category')->create([
            'cover_photo_path' => null,
            'cover_art' => 'troca-de-oleo',
        ]);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('data-scene="troca-de-oleo"', false);
    }

    public function test_generated_art_on_cards_omits_the_duplicated_label(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Documentação', 'slug' => 'documentacao']);
        BlogPost::factory()->for($category, 'category')->create(['cover_photo_path' => null]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('aria-label="Ilustração de Documentação"', false)
            ->assertDontSee('>DOCUMENTAÇÃO</text>', false);
    }
}

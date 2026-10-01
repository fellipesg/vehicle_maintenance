<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Editor do artigo: duas colunas (texto × Publicação, Endereço, Categoria, Capa e SEO), bloco
 * Publicação com Rascunho / Publicar agora / Agendar, contadores, prévia do endereço e a
 * pré-visualização do Markdown pelo endpoint do admin, que não grava nada.
 */
class AdminBlogEditorTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-15 10:00:00'));
    }

    public function test_editor_has_the_text_column_and_the_publication_sidebar(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.create')));

        $form = $this->adminElement($xpath, '//form[@data-admin-blog-editor]');
        $this->assertSame(route('admin.blog.preview'), $form->getAttribute('data-preview-url'));

        $sections = array_map(
            fn ($section): string => $this->adminText($this->adminElement($xpath, '//*[@id="'.$section->getAttribute('aria-labelledby').'"]')),
            $this->adminElements($xpath, './/section[@aria-labelledby]', $form),
        );
        $this->assertSame(['Texto do artigo', 'Publicação', 'Endereço', 'Categoria', 'Capa'], $sections);

        $modes = [];
        foreach ($this->adminElements($xpath, './/input[@type="radio"][@name="publish_mode"]', $form) as $radio) {
            $modes[$radio->getAttribute('value')] = $radio->hasAttribute('checked');
        }
        $this->assertSame(['draft' => true, 'now' => false, 'schedule' => false], $modes, 'Artigo novo começa como rascunho.');
        $nowCard = $this->adminElement($xpath, './/label[@for="publish_mode_now"][@data-slot="radio-card"]', $form);
        $this->assertStringStartsWith('Publicar agora', $this->adminText($nowCard), 'Situação em cartões (x-ui.radio-cards).');
        $this->assertSame(3, $xpath->query('.//*[@data-slot="radio-cards"]/label[@data-slot="radio-card"]', $form)->length);

        $tabs = array_map(fn ($tab): string => $this->adminText($tab), $this->adminElements($xpath, './/*[@role="tablist"][@aria-label="Conteúdo do artigo"]/*[@role="tab"]', $form));
        $this->assertSame(['Escrever', 'Pré-visualizar'], $tabs);

        $this->assertSame('0/160', $this->adminText($this->adminElement($xpath, './/*[@data-char-counter-for="title"]', $form)));
        $this->assertTrue($this->adminElement($xpath, './/input[@id="title"]', $form)->hasAttribute('data-char-count'));
        $this->assertStringEndsWith('/blog/', $this->adminElement($xpath, './/*[@data-admin-blog-slug-preview]', $form)->getAttribute('data-prefix'));

        $save = $this->adminElement($xpath, './/section[@data-admin-blog-publication]//button[@type="submit"]', $form);
        $this->assertSame('Salvar artigo', $this->adminText($save));
    }

    public function test_publish_mode_starts_from_the_post_situation(): void
    {
        $admin = $this->adminUser();
        $live = BlogPost::factory()->create(['published_at' => Carbon::parse('2026-09-01 08:30:00')]);
        $scheduled = BlogPost::factory()->scheduled()->create();
        $draft = BlogPost::factory()->draft()->create();

        $liveXpath = $this->adminPage($this->actingAs($admin)->get(route('admin.blog.edit', $live)));
        $this->assertTrue($this->adminElement($liveXpath, '//input[@name="publish_mode"][@value="now"]')->hasAttribute('checked'));
        $this->assertStringStartsWith('Publicado', $this->adminText($this->adminElement($liveXpath, '//label[@for="publish_mode_now"]')));
        $this->assertSame('No ar desde 01/09/2026 às 08:30.', $this->adminText($this->adminElement($liveXpath, '//*[@id="publish_mode_now-description"]')));
        $this->assertStringContainsString('Ver no site', $this->adminText($this->adminElement($liveXpath, '//header[@data-slot="page-header"]//a[@target="_blank"]')));

        $scheduledXpath = $this->adminPage($this->actingAs($admin)->get(route('admin.blog.edit', $scheduled)));
        $this->assertTrue($this->adminElement($scheduledXpath, '//input[@name="publish_mode"][@value="schedule"]')->hasAttribute('checked'));
        $this->assertSame($scheduled->published_at->format('Y-m-d\TH:i'), $this->adminElement($scheduledXpath, '//input[@name="published_at"]')->getAttribute('value'));

        $draftXpath = $this->adminPage($this->actingAs($admin)->get(route('admin.blog.edit', $draft)));
        $this->assertTrue($this->adminElement($draftXpath, '//input[@name="publish_mode"][@value="draft"]')->hasAttribute('checked'));
        $this->assertStringContainsString('Pré-visualizar no site', $this->adminText($this->adminElement($draftXpath, '//header[@data-slot="page-header"]//a[@target="_blank"]')));
    }

    public function test_publish_modes_save_status_and_date(): void
    {
        $admin = $this->adminUser();
        $payload = ['title' => 'Freios', 'content' => 'Texto.'];

        $this->actingAs($admin)->post(route('admin.blog.store'), [...$payload, 'publish_mode' => 'draft', 'published_at' => '2026-10-01T09:00'])->assertSessionHasNoErrors();
        $post = BlogPost::firstWhere('title', 'Freios');
        $this->assertSame(BlogPost::STATUS_DRAFT, $post->status);
        $this->assertNull($post->published_at, 'Rascunho nunca guarda data.');

        $this->actingAs($admin)->put(route('admin.blog.update', $post), [...$payload, 'publish_mode' => 'schedule', 'published_at' => '2026-10-01T09:00'])->assertSessionHasNoErrors();
        $post->refresh();
        $this->assertSame(BlogPost::STATUS_PUBLISHED, $post->status);
        $this->assertSame('2026-10-01 09:00', $post->published_at->format('Y-m-d H:i'));
        $this->assertFalse($post->isPublished());

        $this->actingAs($admin)->put(route('admin.blog.update', $post), [...$payload, 'publish_mode' => 'now'])->assertSessionHasNoErrors();
        $post->refresh();
        $this->assertTrue($post->isPublished());
        $this->assertSame('2026-09-15 10:00', $post->published_at->format('Y-m-d H:i'));

        $this->travel(2)->days();
        $this->actingAs($admin)->put(route('admin.blog.update', $post), [...$payload, 'publish_mode' => 'now'])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-15 10:00', $post->fresh()->published_at->format('Y-m-d H:i'), 'Artigo já no ar mantém a data original.');
    }

    public function test_scheduling_needs_a_future_date(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->from(route('admin.blog.create'))
            ->post(route('admin.blog.store'), ['title' => 'Agendado', 'content' => 'Texto.', 'publish_mode' => 'schedule'])
            ->assertSessionHasErrors(['published_at']);

        $this->actingAs($admin)
            ->post(route('admin.blog.store'), ['title' => 'Agendado', 'content' => 'Texto.', 'publish_mode' => 'schedule', 'published_at' => '2026-09-01T09:00'])
            ->assertSessionHasErrors(['published_at']);

        $this->actingAs($admin)
            ->post(route('admin.blog.store'), ['title' => 'Agendado', 'content' => 'Texto.', 'publish_mode' => 'amanhã'])
            ->assertSessionHasErrors(['publish_mode']);

        $this->assertSame(0, BlogPost::count());
    }

    public function test_preview_renders_markdown_like_the_article_and_saves_nothing(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.blog.preview'), ['content' => "## Troca de óleo\n\nUse **óleo sintético**."])
            ->assertOk();

        $response->assertSee('<div class="blog-content">', false)
            ->assertSee('<h2>Troca de óleo</h2>', false)
            ->assertSee('<strong>óleo sintético</strong>', false)
            ->assertDontSee('<html', false);

        $this->actingAs($this->adminUser())
            ->post(route('admin.blog.preview'), ['content' => ''])
            ->assertOk()
            ->assertSee('Escreva o conteúdo para ver a pré-visualização.');

        $this->assertSame(0, BlogPost::count());
    }

    public function test_preview_is_only_for_admins(): void
    {
        $this->post(route('admin.blog.preview'), ['content' => 'Oi'])->assertRedirect(route('login.admin'));

        $this->actingAs(User::factory()->asUser()->create())
            ->post(route('admin.blog.preview'), ['content' => 'Oi'])
            ->assertRedirect(route('user.dashboard'));
    }

    public function test_preview_tab_shows_the_saved_version_without_javascript(): void
    {
        $post = BlogPost::factory()->create(['content' => "## Pneus\n\nCalibre toda semana."]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.edit', $post)));

        $panel = $this->adminElement($xpath, '//*[@id="conteudo-previa"][@role="tabpanel"]');
        $this->assertTrue($panel->hasAttribute('hidden'));
        $this->assertStringContainsString('Versão salva', $this->adminText($panel));
        $this->assertSame('Pneus', $this->adminText($this->adminElement($xpath, './/*[contains(@class, "blog-content")]/h2', $panel)));
    }

    public function test_editor_script_wires_counters_preview_schedule_and_unsaved_warning(): void
    {
        $script = file_get_contents(resource_path('js/admin-blog-editor.js'));

        foreach (['[data-char-count]', 'data-admin-blog-slug-value', 'publish_mode', 'ui:tab-change', 'beforeunload', "'X-CSRF-TOKEN'", 'data-preview-url'] as $hook) {
            $this->assertStringContainsString($hook, $script);
        }
        $this->assertStringNotContainsString('window.confirm', $script);
    }
}

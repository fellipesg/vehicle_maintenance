<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Artigos do blog no admin: filtro Todos | Publicados | Agendados | Rascunhos e avisos com o termo
 * do glossário ("Artigo …", "excluído").
 */
class AdminBlogStatusFilterTest extends TestCase
{
    use InspectsAdminPages;
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_each_filter_lists_only_its_posts(): void
    {
        BlogPost::factory()->create(['title' => 'Publicado já']);
        BlogPost::factory()->scheduled()->create(['title' => 'Para amanhã']);
        BlogPost::factory()->draft()->create(['title' => 'Ainda escrevendo']);

        $expectations = [
            '' => ['Publicado já', 'Para amanhã', 'Ainda escrevendo'],
            BlogPost::STATUS_PUBLISHED => ['Publicado já'],
            BlogPost::FILTER_SCHEDULED => ['Para amanhã'],
            BlogPost::STATUS_DRAFT => ['Ainda escrevendo'],
            'qualquer-coisa' => ['Publicado já', 'Para amanhã', 'Ainda escrevendo'],
        ];

        foreach ($expectations as $status => $titles) {
            $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.index', array_filter(['status' => $status]))));
            $listed = array_map(fn ($link) => $this->adminText($link), $this->adminElements($xpath, '//tbody/tr/th//a'));

            $this->assertEqualsCanonicalizing($titles, $listed, "Filtro '{$status}'.");
        }
    }

    public function test_scheduled_filter_is_offered_and_marked_as_current(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.index', ['status' => BlogPost::FILTER_SCHEDULED])));
        $filter = $this->adminElement($xpath, '//nav[@aria-label="Filtrar artigos por situação"]');

        $this->assertSame(
            ['Todos 0', 'Publicados 0', 'Agendados 0', 'Rascunhos 0'],
            array_map(fn ($link) => $this->adminText($link), $this->adminElements($xpath, './/a', $filter)),
        );
        $current = $this->adminElement($xpath, './/a[@aria-current="page"]', $filter);
        $this->assertSame('Agendados 0', $this->adminText($current));
        $this->assertSame(route('admin.blog.index', ['status' => 'scheduled']), $current->getAttribute('href'));
        $this->assertStringContainsString('Nenhum artigo agendado', $this->adminText($this->adminElement($xpath, '//table')));
    }

    public function test_scheduled_scope_leaves_out_published_and_drafts(): void
    {
        $scheduled = BlogPost::factory()->scheduled()->create();
        BlogPost::factory()->create();
        BlogPost::factory()->draft()->create();

        $this->assertSame([$scheduled->id], BlogPost::query()->scheduled()->pluck('id')->all());
    }

    public function test_flash_messages_use_the_article_term(): void
    {
        $admin = $this->adminUser();
        $payload = ['title' => 'Revisão dos freios', 'content' => 'Texto.', 'status' => BlogPost::STATUS_DRAFT];

        $this->actingAs($admin)->post(route('admin.blog.store'), $payload)->assertSessionHas('success', 'Artigo criado.');

        $post = BlogPost::firstWhere('title', 'Revisão dos freios');

        $this->actingAs($admin)->put(route('admin.blog.update', $post), $payload)->assertSessionHas('success', 'Artigo atualizado.');
        $this->actingAs($admin)->delete(route('admin.blog.destroy', $post))->assertSessionHas('success', 'Artigo excluído.');
    }
}

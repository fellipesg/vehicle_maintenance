<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Formulário de artigo com x-ui.field: rótulo ligado ao campo, erros no campo e resumo no topo com
 * links para cada campo com erro.
 */
class AdminBlogFormTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_invalid_article_lists_the_errors_on_top_and_marks_each_field(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->from(route('admin.blog.create'))
            ->followingRedirects()
            ->post(route('admin.blog.store'), ['title' => '', 'content' => '', 'status' => 'draft', 'meta_title' => str_repeat('a', 200)]);

        $xpath = $this->adminPage($response);

        $summary = $this->adminElement($xpath, '//*[@data-slot="form-errors"]');
        $this->assertSame('alert', $summary->getAttribute('role'));
        $targets = array_map(fn ($link): string => $link->getAttribute('href'), $this->adminElements($xpath, './/a[@data-form-errors-link]', $summary));
        $this->assertContains('#title', $targets);
        $this->assertContains('#content', $targets);
        $this->assertContains('#meta_title', $targets);

        foreach (['title', 'content', 'meta_title'] as $field) {
            $control = $this->adminElement($xpath, '//*[@id="'.$field.'"]');
            $this->assertSame('true', $control->getAttribute('aria-invalid'), "{$field} deveria estar marcado como inválido.");
            $this->assertStringContainsString($field.'-error', $control->getAttribute('aria-describedby'));
            $this->adminElement($xpath, '//label[@for="'.$field.'"]');
        }

        $this->assertTrue($this->adminElement($xpath, '//details[.//*[@id="meta_title"]]')->hasAttribute('open'), 'Com erro no SEO, o bloco abre para o link do resumo levar ao campo.');
        $this->assertSame(str_repeat('a', 200), $this->adminElement($xpath, '//*[@id="meta_title"]')->getAttribute('value'));
    }

    public function test_edit_form_keeps_the_saved_values_in_the_new_fields(): void
    {
        $post = BlogPost::factory()->create([
            'title' => 'Pneus no inverno',
            'slug' => 'pneus-no-inverno',
            'excerpt' => 'Calibragem e rodízio.',
            'cover_art' => 'revisao',
        ]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.edit', $post)));

        $this->assertSame('Pneus no inverno', $this->adminElement($xpath, '//input[@id="title"]')->getAttribute('value'));
        $this->assertSame('pneus-no-inverno', $this->adminElement($xpath, '//input[@id="slug"]')->getAttribute('value'));
        $this->assertSame('Calibragem e rodízio.', $this->adminText($this->adminElement($xpath, '//textarea[@id="excerpt"]')));
        $this->assertTrue($this->adminElement($xpath, '//select[@id="cover_art"]/option[@value="revisao"]')->hasAttribute('selected'));
        $this->assertTrue($this->adminElement($xpath, '//input[@type="radio"][@name="publish_mode"][@value="now"]')->hasAttribute('checked'), 'Artigo no ar abre em "Publicado".');
        $this->adminElement($xpath, '//input[@type="file"][@name="cover"][@accept="image/jpeg,image/png,image/webp"]');
    }
}

<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBlogInactiveCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->asUser()->asAdmin()->create();
    }

    public function test_edit_screen_keeps_the_inactive_category_selected_and_flags_it(): void
    {
        $category = BlogCategory::factory()->inactive()->create(['name' => 'Documentos antigos']);
        $post = BlogPost::factory()->for($category, 'category')->create();

        $this->actingAs($this->admin())
            ->get(route('admin.blog.edit', $post))
            ->assertOk()
            ->assertSee('<option value="'.$category->id.'" selected>', false)
            ->assertSee('Documentos antigos (inativa)')
            ->assertSee('Esta categoria está inativa e não aparece na listagem pública.')
            ->assertSee('aria-describedby="blog_category_id_inactive_hint"', false);
    }

    public function test_edit_screen_does_not_list_other_inactive_categories(): void
    {
        $active = BlogCategory::factory()->create(['name' => 'Manutenção preventiva']);
        BlogCategory::factory()->inactive()->create(['name' => 'Categoria arquivada']);
        $post = BlogPost::factory()->for($active, 'category')->create();

        $this->actingAs($this->admin())
            ->get(route('admin.blog.edit', $post))
            ->assertOk()
            ->assertSee('<option value="'.$active->id.'" selected>', false)
            ->assertDontSee('Categoria arquivada')
            ->assertDontSee('(inativa)')
            ->assertDontSee('Esta categoria está inativa');
    }

    public function test_create_screen_lists_only_active_categories(): void
    {
        BlogCategory::factory()->create(['name' => 'Manutenção preventiva']);
        BlogCategory::factory()->inactive()->create(['name' => 'Categoria arquivada']);

        $this->actingAs($this->admin())
            ->get(route('admin.blog.create'))
            ->assertOk()
            ->assertSee('Manutenção preventiva')
            ->assertDontSee('Categoria arquivada');
    }

    public function test_saving_a_post_of_an_inactive_category_keeps_the_category(): void
    {
        $category = BlogCategory::factory()->inactive()->create();
        $post = BlogPost::factory()->for($category, 'category')->create(['title' => 'Título com erro de digitaçao']);

        $this->actingAs($this->admin())
            ->put(route('admin.blog.update', $post), [
                'title' => 'Título com erro de digitação corrigido',
                'blog_category_id' => (string) $category->id,
                'content' => $post->content,
                'status' => $post->status,
            ])
            ->assertRedirect(route('admin.blog.edit', $post->fresh()));

        $post->refresh();

        $this->assertSame($category->id, $post->blog_category_id);
        $this->assertSame('Título com erro de digitação corrigido', $post->title);
    }
}

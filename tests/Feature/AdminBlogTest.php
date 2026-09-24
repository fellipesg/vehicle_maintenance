<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBlogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->asUser()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_open_blog_admin(): void
    {
        $user = User::factory()->asUser()->create();

        $this->actingAs($user)
            ->get(route('admin.blog.index'))
            ->assertForbidden();
    }

    public function test_admin_creates_post_with_generated_slug(): void
    {
        $admin = $this->admin();
        $category = BlogCategory::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.blog.store'), [
            'title' => 'Quando trocar o óleo do motor',
            'blog_category_id' => $category->id,
            'excerpt' => 'Intervalo por quilometragem e por tempo.',
            'content' => '## Intervalo\n\nO manual manda.',
            'status' => BlogPost::STATUS_PUBLISHED,
        ]);

        $post = BlogPost::firstWhere('slug', 'quando-trocar-o-oleo-do-motor');

        $this->assertNotNull($post);
        $response->assertRedirect(route('admin.blog.edit', $post));
        $this->assertSame($admin->id, $post->author_id);
        $this->assertSame($category->id, $post->blog_category_id);
        $this->assertTrue($post->isPublished());
    }

    public function test_duplicate_title_gets_unique_slug(): void
    {
        $admin = $this->admin();
        BlogPost::factory()->create(['slug' => 'revisao-programada']);

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'title' => 'Revisão programada',
            'content' => 'Conteúdo do artigo.',
            'status' => BlogPost::STATUS_DRAFT,
        ])->assertRedirect();

        $this->assertDatabaseHas('blog_posts', ['slug' => 'revisao-programada-2']);
    }

    public function test_draft_post_has_no_publication_date(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'title' => 'Rascunho sem data',
            'content' => 'Texto.',
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $post = BlogPost::firstWhere('slug', 'rascunho-sem-data');

        $this->assertNull($post->published_at);
        $this->assertFalse($post->isPublished());
    }

    public function test_admin_uploads_and_removes_cover(): void
    {
        $this->fakeCoversDisk();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'title' => 'Post com capa',
            'content' => 'Texto.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'cover' => UploadedFile::fake()->image('capa.jpg', 1200, 630),
        ])->assertRedirect();

        $post = BlogPost::firstWhere('slug', 'post-com-capa');

        $this->assertNotNull($post->cover_photo_path);
        $this->assertStringStartsWith(AppStorage::BLOG_COVERS_PREFIX, $post->cover_photo_path);
        Storage::disk('covers')->assertExists($post->cover_photo_path);

        $coverPath = $post->cover_photo_path;

        $this->actingAs($admin)->put(route('admin.blog.update', $post), [
            'title' => $post->title,
            'content' => $post->content,
            'status' => $post->status,
            'remove_cover' => '1',
        ])->assertRedirect();

        Storage::disk('covers')->assertMissing($coverPath);
        $this->assertNull($post->fresh()->cover_photo_path);
    }

    public function test_admin_updates_and_deletes_post(): void
    {
        $admin = $this->admin();
        $post = BlogPost::factory()->create(['title' => 'Título antigo']);

        $this->actingAs($admin)->put(route('admin.blog.update', $post), [
            'title' => 'Título novo',
            'content' => 'Conteúdo atualizado.',
            'status' => BlogPost::STATUS_PUBLISHED,
        ])->assertRedirect();

        $post->refresh();
        $this->assertSame('Título novo', $post->title);
        $this->assertSame('titulo-novo', $post->slug);

        $this->actingAs($admin)
            ->delete(route('admin.blog.destroy', $post))
            ->assertRedirect(route('admin.blog.index'));

        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    public function test_admin_manages_categories(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.categories.store'), [
            'name' => 'Documentação',
            'description' => 'CRLV, IPVA e licenciamento.',
        ])->assertRedirect(route('admin.blog.categories.index'));

        $category = BlogCategory::firstWhere('slug', 'documentacao');
        $this->assertNotNull($category);
        $this->assertTrue($category->is_active);

        $this->actingAs($admin)->put(route('admin.blog.categories.update', $category), [
            'name' => 'Documentos',
            'is_active' => '0',
        ])->assertRedirect();

        $category->refresh();
        $this->assertSame('documentos', $category->slug);
        $this->assertFalse($category->is_active);
    }

    public function test_deleting_category_keeps_its_posts(): void
    {
        $admin = $this->admin();
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->for($category, 'category')->create();

        $this->actingAs($admin)
            ->delete(route('admin.blog.categories.destroy', $category))
            ->assertRedirect(route('admin.blog.categories.index'));

        $this->assertDatabaseHas('blog_posts', ['id' => $post->id, 'blog_category_id' => null]);
    }

    public function test_admin_chooses_the_cover_illustration(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'title' => 'Post com cena escolhida',
            'content' => 'Texto.',
            'status' => BlogPost::STATUS_DRAFT,
            'cover_art' => 'troca-de-oleo',
        ])->assertRedirect();

        $this->assertSame('troca-de-oleo', BlogPost::firstWhere('slug', 'post-com-cena-escolhida')->cover_art);
    }

    public function test_unknown_cover_illustration_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.blog.store'), [
            'title' => 'Post com cena inválida',
            'content' => 'Texto.',
            'status' => BlogPost::STATUS_DRAFT,
            'cover_art' => 'foguete',
        ])->assertSessionHasErrors('cover_art');

        $this->assertDatabaseMissing('blog_posts', ['slug' => 'post-com-cena-invalida']);
    }

    public function test_create_screen_renders_without_a_post(): void
    {
        BlogCategory::factory()->create(['name' => 'Manutenção', 'slug' => 'manutencao']);

        $this->actingAs($this->admin())
            ->get(route('admin.blog.create'))
            ->assertOk()
            ->assertSee('Ilustração de capa')
            ->assertSee('Automática pela categoria');
    }

    public function test_edit_screen_shows_the_illustration_picker_and_preview(): void
    {
        $admin = $this->admin();
        $category = BlogCategory::factory()->create(['name' => 'Manutenção', 'slug' => 'manutencao']);
        $post = BlogPost::factory()->for($category, 'category')->create([
            'cover_photo_path' => null,
            'cover_art' => 'troca-de-oleo',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.blog.edit', $post))
            ->assertOk()
            ->assertSee('Ilustração de capa')
            ->assertSee('Motor aberto e funil de óleo')
            ->assertSee('data-scene="troca-de-oleo"', false);
    }
}

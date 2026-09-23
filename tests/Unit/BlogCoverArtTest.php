<?php

namespace Tests\Unit;

use App\Models\BlogCategory;
use App\Support\BlogCoverArt;
use PHPUnit\Framework\TestCase;

class BlogCoverArtTest extends TestCase
{
    private function category(string $slug, string $name = 'Categoria'): BlogCategory
    {
        $category = new BlogCategory;
        $category->slug = $slug;
        $category->name = $name;

        return $category;
    }

    public function test_known_categories_have_their_own_scene(): void
    {
        $expected = [
            'plataforma' => 'historico',
            'manutencao' => 'revisao',
            'documentacao' => 'documento',
            'compra-e-venda' => 'venda',
        ];

        foreach ($expected as $slug => $scene) {
            $art = BlogCoverArt::for($this->category($slug));

            $this->assertSame($scene, $art['scene'], "Cena inesperada para {$slug}");
        }
    }

    public function test_post_scene_overrides_the_category_default(): void
    {
        $art = BlogCoverArt::for($this->category('manutencao'), 'troca-de-oleo');

        $this->assertSame('troca-de-oleo', $art['scene']);
    }

    public function test_unknown_scene_is_ignored(): void
    {
        $art = BlogCoverArt::for($this->category('manutencao'), 'foguete');

        $this->assertSame('revisao', $art['scene']);
        $this->assertFalse(BlogCoverArt::isScene('foguete'));
    }

    public function test_every_scene_has_a_component(): void
    {
        foreach (array_keys(BlogCoverArt::SCENES) as $scene) {
            $this->assertFileExists(
                __DIR__.'/../../resources/views/components/blog/art/'.$scene.'.blade.php',
                "Falta o componente da cena {$scene}",
            );
        }
    }

    public function test_unknown_category_falls_back_to_default_icon(): void
    {
        $art = BlogCoverArt::for($this->category('seguranca-veicular', 'Segurança veicular'));

        $this->assertSame(BlogCoverArt::DEFAULT_SCENE, $art['scene']);
        $this->assertSame('Segurança veicular', $art['label']);
    }

    public function test_unknown_category_palette_is_deterministic(): void
    {
        $first = BlogCoverArt::for($this->category('seguranca-veicular'));
        $second = BlogCoverArt::for($this->category('seguranca-veicular'));

        $this->assertSame($first, $second);
    }

    public function test_post_without_category_still_gets_art(): void
    {
        $art = BlogCoverArt::for(null);

        $this->assertSame(BlogCoverArt::DEFAULT_SCENE, $art['scene']);
        $this->assertSame('RevisaLog', $art['label']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $art['accent']);
    }

    public function test_every_palette_is_complete(): void
    {
        foreach (['plataforma', 'manutencao', 'documentacao', 'compra-e-venda', 'inexistente'] as $slug) {
            $art = BlogCoverArt::for($this->category($slug));

            foreach (['scene', 'from', 'to', 'accent', 'label'] as $key) {
                $this->assertArrayHasKey($key, $art, "Faltou {$key} em {$slug}");
            }
        }
    }
}

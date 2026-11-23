<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\DisplayTime;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Publica os quatro posts de resposta direta.
 *
 * updateOrCreate de propósito: a primeira execução cria, e uma execução nova
 * atualiza o texto desses slugs. Não mexe na data de publicação dos outros
 * posts. Não rode o BlogSeeder de novo para "atualizar" o blog: ele recoloca
 * published_at em now().
 */
class AeoBlogPostsSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];

        foreach (BlogSeeder::categories() as $category) {
            $categories[$category['slug']] = BlogCategory::firstOrCreate(
                ['slug' => $category['slug']],
                $category + ['is_active' => true],
            );
        }

        self::refreshExistingPosts();

        foreach (self::posts() as $post) {
            BlogPost::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'blog_category_id' => $categories[$post['category']]->id,
                    'cover_art' => $post['art'],
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'meta_description' => $post['meta_description'],
                    'status' => BlogPost::STATUS_PUBLISHED,
                    'published_at' => DisplayTime::fromLocalInput($post['publish_at']),
                ],
            );
        }
    }

    /**
     * @return list<array{slug: string, publish_at: string, category: string, art: string, title: string, excerpt: string, meta_description: string, content: string}>
     */
    public static function posts(): array
    {
        $posts = require database_path('data/aeo_posts.php');

        return array_map(static function (array $post): array {
            $path = database_path("data/aeo_posts/{$post['slug']}.md");

            if (! is_file($path)) {
                throw new RuntimeException("Post sem corpo: {$path}");
            }

            return $post + ['content' => trim((string) file_get_contents($path))];
        }, $posts);
    }

    /**
     * Atualiza o texto dos posts que já existiam, sem mexer em published_at.
     * O histórico de carro usado vive no Markdown da série: o seeder da série
     * não sobrescreve post existente, então a cópia nova entra por aqui.
     */
    public static function refreshExistingPosts(): void
    {
        $slugs = [
            'como-o-revisalog-guarda-o-historico-do-seu-carro',
            'selo-da-oficina-manutencao-verificada',
            'vender-o-carro-com-historico-de-manutencao',
        ];

        foreach (BlogSeeder::posts() as $post) {
            if (! in_array($post['slug'], $slugs, true)) {
                continue;
            }

            BlogPost::query()->where('slug', $post['slug'])->update([
                'content' => $post['content'],
                'excerpt' => $post['excerpt'],
            ]);
        }

        $historyPath = database_path('data/blog_series/historico-de-carro-usado-como-consultar.md');

        BlogPost::query()->where('slug', 'historico-de-carro-usado-como-consultar')->update([
            'content' => trim((string) file_get_contents($historyPath)),
        ]);
    }
}

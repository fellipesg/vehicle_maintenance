<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\DisplayTime;
use Illuminate\Database\Seeder;
use RuntimeException;

class BlogSeriesSeeder extends Seeder
{
    /**
     * Publica a série de posts de database/data/blog_series.php.
     *
     * Cada post entra com status publicado e a data do calendário: os de data passada já aparecem
     * no blog, os de data futura ficam agendados e entram no ar sozinhos. Só cria o que falta: um
     * post que já existe (pelo slug) não é tocado, para não desfazer edições feitas no admin.
     */
    public function run(): void
    {
        $categories = [];

        foreach (BlogSeeder::categories() as $category) {
            $categories[$category['slug']] = BlogCategory::firstOrCreate(
                ['slug' => $category['slug']],
                $category + ['is_active' => true],
            );
        }

        foreach (self::posts() as $post) {
            BlogPost::firstOrCreate(
                ['slug' => $post['slug']],
                [
                    'blog_category_id' => $categories[$post['category']]->id,
                    'cover_art' => $post['art'],
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'meta_title' => $post['meta_title'],
                    'meta_description' => $post['meta_description'],
                    'status' => BlogPost::STATUS_PUBLISHED,
                    'published_at' => DisplayTime::fromLocalInput($post['publish_at']),
                ],
            );
        }
    }

    /**
     * Os posts da série, com o corpo em Markdown lido de database/data/blog_series/{slug}.md.
     *
     * @return list<array{slug: string, publish_at: string, category: string, art: string, title: string, meta_title: string, excerpt: string, meta_description: string, content: string}>
     */
    public static function posts(): array
    {
        $posts = require database_path('data/blog_series.php');

        return array_map(static function (array $post): array {
            $path = database_path("data/blog_series/{$post['slug']}.md");

            if (! is_file($path)) {
                throw new RuntimeException("Post sem corpo: {$path}");
            }

            return $post + ['content' => trim((string) file_get_contents($path))];
        }, $posts);
    }
}

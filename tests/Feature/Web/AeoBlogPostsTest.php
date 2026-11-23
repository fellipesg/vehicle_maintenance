<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use App\Support\BlogCoverArt;
use App\Support\Seo\BlogArticle;
use App\Support\Seo\CanonicalUrl;
use Database\Seeders\AeoBlogPostsSeeder;
use Database\Seeders\BlogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AeoBlogPostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlogSeeder::class);
        $this->seed(AeoBlogPostsSeeder::class);
    }

    public function test_the_four_answers_are_published_with_a_real_date_and_matching_faq(): void
    {
        $posts = AeoBlogPostsSeeder::posts();

        $this->assertCount(4, $posts);

        foreach ($posts as $post) {
            $slug = $post['slug'];
            $this->assertLessThanOrEqual(155, mb_strlen($post['meta_description']), $slug);
            $this->assertLessThanOrEqual(300, mb_strlen($post['excerpt']), $slug);
            $this->assertTrue(BlogCoverArt::isScene($post['art']), $slug);

            $split = BlogArticle::split($post['content']);
            $this->assertNotNull($split['lead'], $slug);
            $words = BlogArticle::wordCount((string) $split['lead']);
            $this->assertGreaterThanOrEqual(40, $words, $slug);
            $this->assertLessThanOrEqual(60, $words, $slug);

            $faqs = BlogArticle::faqs($split['body']);
            $this->assertGreaterThanOrEqual(3, count($faqs), $slug);
            $this->assertLessThanOrEqual(5, count($faqs), $slug);

            $row = BlogPost::query()->where('slug', $slug)->firstOrFail();
            $this->assertSame('2026-11-23 03:00:00', $row->published_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertTrue($row->isPublished());

            $response = $this->get(route('blog.show', $row))->assertOk();
            $response->assertSee($split['lead'], false);
            $response->assertSee('Felipe Gonçalves', false);
            $response->assertSee('"@type":"FAQPage"', false);
            $response->assertDontSee('"image":null', false);
            $response->assertDontSee('Android disponível', false);

            foreach ($faqs as $faq) {
                $response->assertSee($faq['question'], false);
                $response->assertSee($faq['parts'][0]['text'], false);
            }

            $canonical = CanonicalUrl::named('blog.show', $row);
            $this->get(route('sitemap'))->assertOk()->assertSee('<loc>'.$canonical.'</loc>', false);
            $this->get(route('blog.feed'))->assertOk()->assertSee($row->title, false);
            $this->get(route('blog.category', $row->category))->assertOk()->assertSee($row->title, false);
            $this->get(route('blog.index'))->assertOk()->assertSee($row->title, false);
            $this->get(route('llms'))->assertOk()->assertSee($canonical, false);

            preg_match_all('#\]\((/[^)\s]+)\)#', $post['content'], $links);
            foreach ($links[1] as $path) {
                $this->get($path)->assertOk();
            }
        }
    }

    public function test_running_the_seeder_again_keeps_the_publish_date_and_refreshes_the_copy(): void
    {
        BlogPost::query()->where('slug', 'planilha-ou-aplicativo-manutencao-do-carro')->update([
            'title' => 'Título antigo',
        ]);

        $this->seed(AeoBlogPostsSeeder::class);

        $post = BlogPost::query()->where('slug', 'planilha-ou-aplicativo-manutencao-do-carro')->firstOrFail();

        $this->assertSame('Planilha ou aplicativo para a manutenção do carro?', $post->title);
        $this->assertSame('2026-11-23 03:00:00', $post->published_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(4, BlogPost::query()->whereIn('slug', array_column(AeoBlogPostsSeeder::posts(), 'slug'))->count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\BlogCoverArt;
use Database\Seeders\BlogSeeder;
use Database\Seeders\BlogSeriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BlogSeriesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_from_past_dates_go_live_and_the_rest_stay_scheduled(): void
    {
        $this->travelTo(Carbon::parse('2026-11-24 09:00', 'America/Sao_Paulo'));

        $this->seed(BlogSeriesSeeder::class);

        $this->assertSame(22, BlogPost::count());
        $this->assertEqualsCanonicalizing(
            ['historico-de-carro-usado-como-consultar', 'chassi-onde-fica-como-ler', 'quilometragem-adulterada-sinais'],
            BlogPost::query()->published()->pluck('slug')->all(),
        );
        $this->assertSame(19, BlogPost::query()->scheduled()->count());

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Como consultar o histórico de um carro usado antes de comprar')
            ->assertDontSee('Checklist para ver um carro usado pessoalmente');
        $this->get(route('blog.show', 'quilometragem-adulterada-sinais'))->assertOk();
        $this->get(route('blog.show', 'checklist-carro-usado'))->assertNotFound();
    }

    public function test_publication_time_is_eight_in_the_morning_in_brasilia(): void
    {
        $this->seed(BlogSeriesSeeder::class);

        $post = BlogPost::where('slug', 'historico-de-carro-usado-como-consultar')->firstOrFail();

        $this->assertSame('2026-11-17 11:00:00', $post->published_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_scheduled_post_goes_live_by_itself_when_its_time_arrives(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 07:59', 'America/Sao_Paulo'));
        $this->seed(BlogSeriesSeeder::class);

        $this->get(route('blog.show', 'checklist-carro-usado'))->assertNotFound();

        $this->travelTo(Carbon::parse('2026-11-26 08:00', 'America/Sao_Paulo'));

        $this->get(route('blog.show', 'checklist-carro-usado'))->assertOk();
    }

    public function test_rerunning_does_not_duplicate_nor_overwrite_admin_edits(): void
    {
        $this->seed(BlogSeriesSeeder::class);
        BlogPost::where('slug', 'chassi-onde-fica-como-ler')->update(['title' => 'Título editado no admin']);

        $this->seed(BlogSeriesSeeder::class);

        $this->assertSame(22, BlogPost::count());
        $this->assertSame(4, BlogCategory::count());
        $this->assertSame('Título editado no admin', BlogPost::where('slug', 'chassi-onde-fica-como-ler')->value('title'));
    }

    public function test_reuses_the_existing_categories_and_posts_of_the_blog_seeder(): void
    {
        $this->seed(BlogSeeder::class);
        $this->seed(BlogSeriesSeeder::class);

        $this->assertSame(4, BlogCategory::count());
        $this->assertSame(28, BlogPost::count());
    }

    public function test_every_post_fits_the_editorial_limits(): void
    {
        $posts = BlogSeriesSeeder::posts();
        $categories = array_column(BlogSeeder::categories(), 'slug');

        $this->assertCount(22, $posts);
        $this->assertCount(22, array_unique(array_column($posts, 'slug')));

        foreach ($posts as $post) {
            $slug = $post['slug'];
            $publishAt = Carbon::parse($post['publish_at'], 'America/Sao_Paulo');

            $this->assertContains($post['category'], $categories, $slug);
            $this->assertTrue(BlogCoverArt::isScene($post['art']), $slug);
            $this->assertContains($publishAt->dayOfWeek, [Carbon::TUESDAY, Carbon::THURSDAY], $slug);
            $this->assertSame('08:00', $publishAt->format('H:i'), $slug);
            $this->assertLessThanOrEqual(60, mb_strlen($post['meta_title']), $slug);
            $this->assertLessThanOrEqual(155, mb_strlen($post['meta_description']), $slug);
            $this->assertLessThanOrEqual(300, mb_strlen($post['excerpt']), $slug);
            $this->assertGreaterThan(700, count(preg_split('/\s+/', $post['content'])), $slug);
        }
    }

    /**
     * Um post agendado responde 404 até a data chegar, então nenhum post pode linkar um post da
     * série que sai depois dele.
     */
    public function test_internal_links_only_point_to_posts_already_live_on_that_date(): void
    {
        $posts = BlogSeriesSeeder::posts();
        $existingSlugs = array_column(BlogSeeder::posts(), 'slug');
        $publishAtBySlug = array_column($posts, 'publish_at', 'slug');

        foreach ($posts as $post) {
            preg_match_all('#\]\(/blog/([a-z0-9-]+)\)#', $post['content'], $matches);

            foreach ($matches[1] as $linked) {
                if (in_array($linked, $existingSlugs, true)) {
                    continue;
                }

                $this->assertArrayHasKey($linked, $publishAtBySlug, "{$post['slug']} linka /blog/{$linked}, que não existe");
                $this->assertLessThan($post['publish_at'], $publishAtBySlug[$linked], "{$post['slug']} linka {$linked}, que sai depois");
            }
        }
    }
}

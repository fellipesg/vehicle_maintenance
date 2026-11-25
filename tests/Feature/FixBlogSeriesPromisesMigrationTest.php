<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Database\Seeders\BlogSeriesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionClass;
use Tests\TestCase;

class FixBlogSeriesPromisesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        $files = glob(database_path('migrations/*_fix_blog_series_promises_and_public_search_wording.php')) ?: [];
        $this->assertCount(1, $files);

        return require $files[0];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function replacements(): array
    {
        return (new ReflectionClass($this->migration()))->getConstant('REPLACEMENTS');
    }

    private function postWithOldText(string $slug, string $status = BlogPost::STATUS_PUBLISHED, ?Carbon $publishedAt = null): BlogPost
    {
        $content = "Início.\n\n";

        foreach ($this->replacements() as [$replacementSlug, $old]) {
            if ($replacementSlug === $slug) {
                $content .= $old."\n\n";
            }
        }

        return BlogPost::factory()->create([
            'slug' => $slug,
            'content' => $content.'Fim.',
            'status' => $status,
            'published_at' => $publishedAt ?? now()->subWeek(),
            'updated_at' => Carbon::parse('2026-11-20 10:00:00'),
        ]);
    }

    public function test_it_replaces_only_the_old_sentences_in_published_and_scheduled_rows(): void
    {
        $published = $this->postWithOldText('quilometragem-adulterada-sinais');
        $scheduled = $this->postWithOldText('velas-de-ignicao-quando-trocar', publishedAt: now()->addMonth());
        $publishedAt = $published->published_at->toDateTimeString();

        $this->migration()->up();

        $published->refresh();
        $scheduled->refresh();

        $this->assertStringNotContainsString('post futuro', $published->content);
        $this->assertStringContainsString('semana e ano de fabricação', $published->content);
        $this->assertStringContainsString('Com uma conta gratuita, use a [busca do RevisaLog](/buscar-veiculo)', $published->content);
        $this->assertStringStartsWith("Início.\n\n", $published->content);
        $this->assertStringEndsWith('Fim.', $published->content);
        $this->assertStringNotContainsString('próximo post desta série', $scheduled->content);
        $this->assertStringContainsString('manual do proprietário', $scheduled->content);

        $this->assertSame('2026-11-20 10:00:00', $published->updated_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-20 10:00:00', $scheduled->updated_at->format('Y-m-d H:i:s'));
        $this->assertSame($publishedAt, $published->published_at->toDateTimeString());
        $this->assertTrue($scheduled->published_at->isFuture());
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $post = $this->postWithOldText('quilometragem-adulterada-sinais');

        $this->migration()->up();
        $once = $post->fresh()->content;
        $this->migration()->up();

        $this->assertSame($once, $post->fresh()->content);
    }

    public function test_a_post_edited_by_hand_is_left_alone(): void
    {
        $edited = BlogPost::factory()->create([
            'slug' => 'quilometragem-adulterada-sinais',
            'content' => 'Texto reescrito no admin, sem as frases antigas.',
            'updated_at' => Carbon::parse('2026-11-20 10:00:00'),
        ]);

        $this->migration()->up();

        $edited->refresh();
        $this->assertSame('Texto reescrito no admin, sem as frases antigas.', $edited->content);
        $this->assertSame('2026-11-20 10:00:00', $edited->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_missing_rows_are_a_no_op(): void
    {
        $this->assertSame(0, BlogPost::count());

        $this->migration()->up();

        $this->assertSame(0, BlogPost::count());
    }

    public function test_new_seed_already_carries_the_new_text_so_the_migration_has_nothing_to_do(): void
    {
        $this->seed(BlogSeriesSeeder::class);
        $before = BlogPost::query()->orderBy('id')->pluck('content', 'slug')->all();

        $this->migration()->up();

        $this->assertSame($before, BlogPost::query()->orderBy('id')->pluck('content', 'slug')->all());

        foreach ($this->replacements() as [$slug, $old, $new]) {
            $this->assertStringNotContainsString($old, $before[$slug]);
            $this->assertStringContainsString($new, $before[$slug]);
        }
    }
}

<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use App\Models\User;
use App\Support\Seo\CanonicalUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogAuthorAndDatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function blogPosting(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);

            if (($decoded['@type'] ?? null) === 'BlogPosting') {
                return $decoded;
            }
        }

        $this->fail('BlogPosting JSON-LD não encontrado.');
    }

    public function test_post_without_stored_author_is_signed_by_felipe_with_a_link_to_the_author_section(): void
    {
        $post = BlogPost::factory()->create(['author_id' => null]);
        $anchor = CanonicalUrl::named('about').'#autor';

        $response = $this->get(route('blog.show', $post))->assertOk();

        $response->assertSee('data-slot="blog-post-author"', false)
            ->assertSee('href="'.route('about').'#autor"', false)
            ->assertSee('Felipe Gonçalves', false);

        $author = $this->blogPosting((string) $response->getContent())['author'];
        $this->assertSame('Felipe Gonçalves', $author['name']);
        $this->assertSame($anchor, $author['url']);
    }

    public function test_stored_author_named_felipe_links_regardless_of_accents_and_case(): void
    {
        $author = User::factory()->create(['name' => 'felipe goncalves']);
        $post = BlogPost::factory()->create(['author_id' => $author->id]);

        $response = $this->get(route('blog.show', $post))->assertOk();

        $response->assertSee('href="'.route('about').'#autor"', false);
        $this->assertSame(CanonicalUrl::named('about').'#autor', $this->blogPosting((string) $response->getContent())['author']['url']);
    }

    public function test_other_authors_get_no_profile_link(): void
    {
        $author = User::factory()->create(['name' => 'Maria Souza']);
        $post = BlogPost::factory()->create(['author_id' => $author->id]);

        $response = $this->get(route('blog.show', $post))->assertOk();

        $response->assertSee('por', false)
            ->assertSee('Maria Souza', false)
            ->assertDontSee('href="'.route('about').'#autor"', false);

        $schemaAuthor = $this->blogPosting((string) $response->getContent())['author'];
        $this->assertSame('Maria Souza', $schemaAuthor['name']);
        $this->assertArrayNotHasKey('url', $schemaAuthor);
    }

    public function test_date_modified_is_never_earlier_than_date_published(): void
    {
        $post = BlogPost::factory()->create([
            'published_at' => now()->subDay(),
            'updated_at' => now()->subDays(30),
        ]);

        $response = $this->get(route('blog.show', $post))->assertOk();
        $schema = $this->blogPosting((string) $response->getContent());

        $this->assertSame($post->published_at->toIso8601String(), $schema['datePublished']);
        $this->assertSame($schema['datePublished'], $schema['dateModified']);
        $this->assertTrue($post->fresh()->modified_at->equalTo($post->published_at));
        $response->assertDontSee('data-slot="blog-post-updated"', false);
    }

    public function test_later_revision_is_shown_and_declared_as_modified(): void
    {
        $post = BlogPost::factory()->create([
            'published_at' => now()->subDays(10),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this->get(route('blog.show', $post))->assertOk();
        $schema = $this->blogPosting((string) $response->getContent());

        $this->assertSame($post->updated_at->toIso8601String(), $schema['dateModified']);
        $this->assertGreaterThan($schema['datePublished'], $schema['dateModified']);
        $response->assertSee('data-slot="blog-post-updated"', false);
    }

    public function test_sitemap_lastmod_is_never_earlier_than_publication(): void
    {
        $stale = BlogPost::factory()->create(['published_at' => now()->subDay(), 'updated_at' => now()->subDays(40)]);
        $revised = BlogPost::factory()->create(['published_at' => now()->subDays(10), 'updated_at' => now()->subDays(3)]);

        $xml = (string) $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<loc>'.preg_quote(route('blog.show', $stale), '#').'</loc>\s*<lastmod>'.preg_quote($stale->published_at->toAtomString(), '#').'</lastmod>#',
            $xml,
        );
        $this->assertMatchesRegularExpression(
            '#<loc>'.preg_quote(route('blog.show', $revised), '#').'</loc>\s*<lastmod>'.preg_quote($revised->updated_at->toAtomString(), '#').'</lastmod>#',
            $xml,
        );
    }

    public function test_modified_at_falls_back_when_one_date_is_missing(): void
    {
        $draft = BlogPost::factory()->draft()->create();

        $this->assertTrue($draft->modified_at->equalTo($draft->updated_at));
    }
}

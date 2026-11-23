<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use App\Models\User;
use App\Support\Seo\CanonicalUrl;
use App\Support\Seo\Faq;
use App\Support\Seo\HomeFaq;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Base de SEO público: canonical sem query, noindex nas rotas de conta,
 * JSON-LD e llms.txt. A home canônica termina com barra; as demais, não.
 */
class SeoFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_canonical_has_a_trailing_slash_and_the_definition_schema(): void
    {
        $page = $this->page($this->get(route('home'))->assertOk());

        $canonical = CanonicalUrl::home();
        $this->assertStringEndsWith('/', $canonical);
        $this->assertSame($canonical, $this->element($page, 'link[rel="canonical"]')->getAttribute('href'));
        $this->assertSame($canonical, $this->element($page, 'meta[property="og:url"]')->getAttribute('content'));
        $this->assertSame(1, count($page->querySelectorAll('link[rel="canonical"]')));
        $this->assertSame(1, count($page->querySelectorAll('meta[name="description"]')));
        $this->assertSame(HomeFaq::metaDescription(), $this->element($page, 'meta[name="description"]')->getAttribute('content'));
        $this->assertSame(HomeFaq::definition(), $this->text($this->element($page, '[data-landing-definition]')));
        $this->assertNull($page->querySelector('meta[name="robots"]'));

        $types = $this->jsonLdTypes($page);
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('MobileApplication', $types);
        $this->assertContains('FAQPage', $types);

        $graph = $this->jsonLdByType($page, 'Organization');
        $this->assertSame(CanonicalUrl::organizationId(), $graph['@id']);
        $this->assertNotContains('SearchAction', $types);
        $this->assertArrayNotHasKey('potentialAction', $this->jsonLdByType($page, 'WebSite'));

        $this->assertFaqMatches($page, HomeFaq::items());
        $this->assertLessThanOrEqual(155, mb_strlen(HomeFaq::metaDescription()));

        $answer = Faq::plain(HomeFaq::items()[0]['parts']);
        $words = preg_split('/\s+/u', trim($answer)) ?: [];
        $this->assertSame('O que é o RevisaLog?', HomeFaq::items()[0]['question']);
        $this->assertGreaterThanOrEqual(40, count($words));
        $this->assertLessThanOrEqual(60, count($words));
        $text = (string) $page->documentElement?->textContent;
        $this->assertStringContainsString('app para iPhone', $text);
        $this->assertStringContainsString('Grátis no lançamento', $text);
        $this->assertStringContainsString('Android em breve', $text);
        $this->assertDoesNotMatchRegularExpression('/Android (já |está )?disponível/u', $text);
    }

    public function test_canonical_drops_the_query_string(): void
    {
        $page = $this->page($this->get(route('contact.show', ['assunto' => 'partnership']))->assertOk());

        $this->assertSame(CanonicalUrl::named('contact.show'), $this->element($page, 'link[rel="canonical"]')->getAttribute('href'));
        $this->assertStringNotContainsString('assunto', (string) $this->element($page, 'link[rel="canonical"]')->getAttribute('href'));
    }

    public function test_account_routes_are_noindex_and_the_workshop_signup_stays_indexable(): void
    {
        foreach (['login', 'login.lojista', 'login.usuario', 'login.oficina', 'login.admin', 'register', 'password.request'] as $name) {
            $response = $this->get(route($name))->assertOk();
            $response->assertHeader('X-Robots-Tag', 'noindex, follow');
            $page = $this->page($response);
            $this->assertSame('noindex, follow', $this->element($page, 'meta[name="robots"]')->getAttribute('content'));
        }

        $signup = $this->get(route('workshops.signup'))->assertOk();
        $signup->assertHeaderMissing('X-Robots-Tag');
        $this->assertNull($this->page($signup)->querySelector('meta[name="robots"]'));

        $owner = User::factory()->asUser()->create();
        $dashboard = $this->actingAs($owner)->get(route('user.dashboard'))->assertOk();
        $dashboard->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->assertSame('noindex, follow', $this->element($this->page($dashboard), 'meta[name="robots"]')->getAttribute('content'));
    }

    public function test_sitemap_locs_match_canonicals_and_skip_noindex_routes(): void
    {
        $xml = $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $document = simplexml_load_string($xml->getContent());
        $this->assertNotFalse($document);
        $document->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $locs = [];
        foreach ($document->xpath('//s:url') as $url) {
            $loc = (string) $url->loc;
            $locs[] = $loc;
            $this->assertNotSame('', (string) $url->lastmod, $loc);
            $path = parse_url($loc, PHP_URL_PATH) ?: '/';
            $page = $this->page($this->get($path)->assertOk());
            $this->assertSame($loc, $this->element($page, 'link[rel="canonical"]')->getAttribute('href'));
        }

        $this->assertContains(CanonicalUrl::home(), $locs);
        $this->assertContains(CanonicalUrl::named('workshops.signup'), $locs);
        $this->assertNotContains(CanonicalUrl::named('llms'), $locs);

        $body = $xml->getContent();
        $this->assertStringNotContainsString('/login', $body);
        $this->assertStringNotContainsString('/register', $body);
        $this->assertStringNotContainsString('/buscar-veiculo', $body);
    }

    public function test_robots_txt_does_not_disallow_account_routes(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertIsString($robots);
        $this->assertDoesNotMatchRegularExpression('/Disallow:\s*\/login/i', $robots);
        $this->assertDoesNotMatchRegularExpression('/Disallow:\s*\/register/i', $robots);
    }

    public function test_llms_txt_is_plain_text_and_every_local_link_responds(): void
    {
        $response = $this->get(route('llms'))->assertOk();
        $contentType = (string) $response->headers->get('Content-Type');

        $this->assertStringContainsString('text/plain', $contentType);
        $this->assertStringContainsStringIgnoringCase('charset=utf-8', $contentType);
        $this->assertStringContainsString('RevisaLog é um aplicativo brasileiro para iPhone', $response->getContent());
        $this->assertStringNotContainsString('WhatsApp', $this->reminderClaim($response->getContent()));

        preg_match_all('/\((https?:\/\/[^)\s]+)\)/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host !== parse_url(CanonicalUrl::origin(), PHP_URL_HOST)) {
                continue;
            }

            $path = parse_url($url, PHP_URL_PATH) ?: '/';
            $this->get($path)->assertOk();
        }
    }

    public function test_blog_posting_schema_omits_a_missing_image_and_names_the_author(): void
    {
        $post = BlogPost::factory()->create(['cover_photo_path' => null, 'author_id' => null]);

        $schema = $this->jsonLdByType($this->page($this->get(route('blog.show', $post))->assertOk()), 'BlogPosting');

        $this->assertArrayNotHasKey('image', $schema);
        $this->assertSame('pt-BR', $schema['inLanguage']);
        $this->assertSame('Person', $schema['author']['@type']);
        $this->assertSame('Felipe Gonçalves', $schema['author']['name']);
        $this->assertSame(CanonicalUrl::named('blog.show', $post), $schema['mainEntityOfPage']['@id']);
        $this->assertSame(CanonicalUrl::organizationId(), $schema['publisher']['@id']);
        $this->assertSame('RevisaLog', $schema['publisher']['name']);
    }

    public function test_blog_posting_schema_uses_the_cover_when_the_post_has_one(): void
    {
        $post = BlogPost::factory()->create(['cover_photo_path' => 'blog-covers/capa.jpg']);

        $schema = $this->jsonLdByType($this->page($this->get(route('blog.show', $post))->assertOk()), 'BlogPosting');

        $this->assertSame($post->cover_photo_url, $schema['image']);
    }

    private function reminderClaim(string $body): string
    {
        return (string) preg_replace('/Não envia alertas por WhatsApp.*/', '', $body);
    }

    /**
     * @param  list<array{question: string, parts: list<array{text: string, href?: string}>}>  $items
     */
    private function assertFaqMatches(HTMLDocument $page, array $items): void
    {
        $questions = [];
        foreach ($page->querySelectorAll('details.landing-faq') as $details) {
            $question = $this->text($this->element($details, 'summary'));
            $answer = $this->text($this->element($details, 'summary + div'));
            $questions[$question] = $answer;
        }

        $schema = $this->jsonLdByType($page, 'FAQPage');

        $this->assertCount(count($items), $schema['mainEntity']);

        foreach ($items as $index => $item) {
            $this->assertArrayHasKey($item['question'], $questions);
            $this->assertSame(Faq::plain($item['parts']), $questions[$item['question']]);
            $this->assertSame($item['question'], $schema['mainEntity'][$index]['name']);
            $this->assertSame(Faq::plain($item['parts']), $schema['mainEntity'][$index]['acceptedAnswer']['text']);
        }
    }

    /**
     * @return list<string>
     */
    private function jsonLdTypes(HTMLDocument $page): array
    {
        $types = [];

        foreach ($page->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $decoded = json_decode($script->textContent, true);
            $this->assertIsArray($decoded);
            $types = array_merge($types, $this->collectTypes($decoded));
        }

        return $types;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLdByType(HTMLDocument $page, string $type): array
    {
        foreach ($page->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $decoded = json_decode($script->textContent, true);
            $found = $this->findByType(is_array($decoded) ? $decoded : [], $type);
            if ($found !== null) {
                return $found;
            }
        }

        $this->fail("JSON-LD {$type} não encontrado.");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function collectTypes(array $data): array
    {
        $types = [];
        $type = $data['@type'] ?? null;

        if (is_string($type)) {
            $types[] = $type;
        } elseif (is_array($type)) {
            foreach ($type as $item) {
                if (is_string($item)) {
                    $types[] = $item;
                }
            }
        }

        foreach ($data as $value) {
            if (! is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $types = array_merge($types, $this->collectTypes($item));
                    }
                }
            } else {
                $types = array_merge($types, $this->collectTypes($value));
            }
        }

        return $types;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function findByType(array $data, string $type): ?array
    {
        $current = $data['@type'] ?? null;
        $matches = $current === $type || (is_array($current) && in_array($type, $current, true));

        if ($matches) {
            return $data;
        }

        foreach ($data as $value) {
            if (! is_array($value)) {
                continue;
            }

            $items = array_is_list($value) ? $value : [$value];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $found = $this->findByType($item, $type);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function page(TestResponse $response): HTMLDocument
    {
        return HTMLDocument::createFromString((string) $response->getContent(), LIBXML_NOERROR);
    }

    private function element(HTMLDocument|Element $scope, string $selector): Element
    {
        $element = $scope->querySelector($selector);

        $this->assertInstanceOf(Element::class, $element, "Nenhum elemento casa com {$selector}.");

        return $element;
    }

    private function text(Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }
}

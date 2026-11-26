<?php

namespace Tests\Feature\Web;

use App\Support\Seo\CanonicalUrl;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_is_public_with_seo_head_and_a_single_h1(): void
    {
        $page = $this->page($this->get('/sobre')->assertOk());
        $canonical = CanonicalUrl::named('about');

        $this->assertSame('/sobre', parse_url($canonical, PHP_URL_PATH));
        $this->assertSame(1, count($page->querySelectorAll('h1')));
        $this->assertSame('Sobre o RevisaLog', $this->text($this->element($page, 'h1')));
        $this->assertSame($canonical, $this->element($page, 'link[rel="canonical"]')->getAttribute('href'));
        $this->assertNotSame('', $this->element($page, 'meta[name="description"]')->getAttribute('content'));
        $this->assertNull($page->querySelector('meta[name="robots"]'));

        $types = [];
        foreach ($page->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $decoded = json_decode($script->textContent, true);
            $types[] = $decoded['@type'] ?? null;

            if (($decoded['@type'] ?? null) === 'AboutPage') {
                $this->assertSame($canonical, $decoded['url']);
                $this->assertSame(CanonicalUrl::organizationId(), $decoded['about']['@id']);
            }
        }
        $this->assertContains('AboutPage', $types);
        $this->assertContains('BreadcrumbList', $types);
        $this->assertContains('Organization', array_column((array) json_decode($this->element($page, 'script[type="application/ld+json"]')->textContent, true)['@graph'] ?? [], '@type'));
    }

    public function test_about_page_covers_the_verifiable_topics_without_invented_claims(): void
    {
        $response = $this->get(route('about'))->assertOk();

        foreach (['O que é o RevisaLog', 'Como os registros funcionam', 'Selo da oficina', 'Declarada', 'O que o RevisaLog não garante', 'grátis', 'iPhone', 'Como os artigos do blog são feitos', 'fontes oficiais', 'Contato'] as $text) {
            $response->assertSee($text, false);
        }

        $response->assertSee(route('contact.show'), false)
            ->assertSee(route('verification.lookup'), false)
            ->assertDontSee('anos de experiência', false)
            ->assertDontSee('equipe de', false)
            ->assertDontSee('revisado por', false);
    }

    public function test_author_section_has_the_anchor_name_and_the_bio(): void
    {
        $page = $this->page($this->get(route('about'))->assertOk());
        $section = $this->element($page, 'section#autor');

        $this->assertSame('Felipe Gonçalves', $this->text($this->element($section, '[data-slot="about-author-name"]')));
        $this->assertSame(__('about.author_bio'), $this->text($this->element($section, '[data-slot="about-author-bio"]')));
    }

    public function test_author_section_prints_no_bio_paragraph_when_the_language_line_is_empty(): void
    {
        app('translator')->addLines(['about.author_bio' => ''], 'pt_BR');

        $page = $this->page($this->get(route('about'))->assertOk());

        $this->assertNull($this->element($page, 'section#autor')->querySelector('[data-slot="about-author-bio"]'));
    }

    public function test_about_page_is_in_the_sitemap_footer_and_llms_txt(): void
    {
        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee('<loc>'.CanonicalUrl::named('about').'</loc>', false);

        $this->get(route('home'))->assertOk()->assertSee('href="'.route('about').'"', false);
        $this->get(route('llms'))->assertOk()->assertSee(CanonicalUrl::named('about'), false);
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

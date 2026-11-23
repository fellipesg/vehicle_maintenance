<?php

namespace Tests\Feature\Web;

use App\Support\Seo\AppComparison;
use App\Support\Seo\CanonicalUrl;
use App\Support\Seo\Faq;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ComparisonPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_page_answers_in_the_first_paragraph_and_stays_in_the_index(): void
    {
        $response = $this->get(route('guides.comparison'))->assertOk();
        $page = $this->page($response);
        $canonical = CanonicalUrl::named('guides.comparison');

        $this->assertSame(AppComparison::title(), $this->text($this->element($page, 'h1')));
        $this->assertSame(1, count($page->querySelectorAll('h1')));
        $this->assertSame($canonical, $this->element($page, 'link[rel="canonical"]')->getAttribute('href'));
        $this->assertLessThanOrEqual(155, mb_strlen(AppComparison::metaDescription()));
        $this->assertSame(AppComparison::metaDescription(), $this->element($page, 'meta[name="description"]')->getAttribute('content'));
        $this->assertSame(AppComparison::lead(), $this->text($this->element($page, '[data-slot="comparison-lead"]')));

        $words = preg_split('/\s+/u', trim(AppComparison::lead())) ?: [];
        $this->assertGreaterThanOrEqual(40, count($words));
        $this->assertLessThanOrEqual(60, count($words));

        $this->assertStringContainsString('não informado', $page->documentElement->textContent);
        $this->assertStringContainsString('Atualizado em '.AppComparison::verifiedOnLabel(), $page->documentElement->textContent);
        $this->assertStringContainsString('Android em breve', $page->documentElement->textContent);
        $this->assertStringNotContainsString('app brasileiro', $this->text($this->element($page, '#outros-apps')));
        $this->assertDoesNotMatchRegularExpression('/\d\s*estrelas|nota\s+\d/ui', (string) $page->documentElement->textContent);

        $types = [];
        foreach ($page->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $decoded = json_decode($script->textContent, true);
            $this->assertIsArray($decoded);
            $type = $decoded['@type'] ?? null;
            if (is_string($type)) {
                $types[] = $type;
            }
        }
        $this->assertContains('WebPage', $types);
        $this->assertContains('ItemList', $types);
        $this->assertContains('FAQPage', $types);
        $this->assertContains('BreadcrumbList', $types);

        foreach (AppComparison::faq() as $item) {
            $this->assertStringContainsString($item['question'], (string) $page->documentElement->textContent);
            $this->assertStringContainsString(Faq::plain($item['parts']), (string) $page->documentElement->textContent);
        }

        $home = $this->get(route('home'))->assertOk();
        $home->assertSee('Comparativo de apps', false);
        $home->assertSee(route('guides.comparison'), false);

        $sitemap = $this->get(route('sitemap'))->assertOk();
        $sitemap->assertSee('<loc>'.$canonical.'</loc>', false);
        $sitemap->assertSee('<lastmod>'.AppComparison::verifiedOn().'</lastmod>', false);

        $this->get(route('llms'))->assertOk()->assertSee($canonical, false);
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

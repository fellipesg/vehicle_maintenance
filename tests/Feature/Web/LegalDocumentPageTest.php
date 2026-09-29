<?php

namespace Tests\Feature\Web;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Termos e privacidade (PUB-23, PUB-24): cabeçalho público pelo x-ui.page-header, sumário com
 * âncoras estáveis (recolhível no celular, fixo no desktop) e coluna de leitura de ~72 caracteres por linha.
 */
class LegalDocumentPageTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function documents(): array
    {
        return [
            'termos' => ['legal.terms', 'Termos de uso', 'legal.terms_version'],
            'privacidade' => ['legal.privacy', 'Política de privacidade', 'legal.privacy_version'],
        ];
    }

    #[DataProvider('documents')]
    public function test_header_uses_the_public_page_header_with_context_eyebrow(string $route, string $title, string $versionKey): void
    {
        $page = $this->page($this->get(route($route))->assertOk());
        $header = $this->find($page, 'header[data-slot="page-header"]');

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame($title, $this->text($this->find($header, 'h1')));
        $this->assertSame('Documentos legais', $this->text($this->find($header, '[data-slot="page-header-eyebrow"]')));
        $this->assertStringStartsWith('Em vigor desde', $this->text($this->find($header, '[data-slot="page-header-description"]')));
        $this->assertSame((string) config($versionKey), $this->find($header, 'time')->getAttribute('datetime'));
        $this->assertSame('Versão '.config($versionKey), $this->text($this->find($header, '[data-slot="legal-version"]')));
        $this->assertStringNotContainsString('Versão', $this->text($this->find($header, '[data-slot="page-header-description"]')), 'A versão ISO não repete a data por extenso.');
    }

    #[DataProvider('documents')]
    public function test_every_section_has_a_stable_anchor_listed_in_both_tables_of_contents(string $route): void
    {
        $page = $this->page($this->get(route($route))->assertOk());
        $headings = iterator_to_array($this->find($page, '[data-slot="legal-content"]')->querySelectorAll('h2'));

        $this->assertNotEmpty($headings);

        $ids = array_map(fn (Element $heading): string => $heading->getAttribute('id'), $headings);
        $this->assertSame($ids, array_unique($ids));
        foreach ($headings as $heading) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $heading->getAttribute('id'));
            $this->assertContains('scroll-mt-24', $this->classesOf($heading), 'A âncora não fica escondida atrás da barra fixa.');
        }

        $expectedLinks = array_map(fn (string $id): string => '#'.$id, $ids);
        foreach (['[data-slot="legal-toc"]', '[data-slot="legal-toc-collapsible"] nav'] as $tocSelector) {
            $toc = $this->find($page, $tocSelector);
            $links = array_map(fn (Element $link): string => $link->getAttribute('href'), iterator_to_array($toc->querySelectorAll('a')));
            $this->assertSame($expectedLinks, $links, "Sumário {$tocSelector}");
        }
    }

    public function test_privacy_rights_section_can_be_linked_directly(): void
    {
        $page = $this->page($this->get(route('legal.privacy'))->assertOk());

        $this->assertSame('7. Seus direitos', $this->text($this->find($page, 'h2#seus-direitos')));
        $this->assertSame('7. Seus direitos', $this->text($this->find($page, '[data-slot="legal-toc"] a[href="#seus-direitos"]')));
    }

    public function test_table_of_contents_is_collapsible_on_small_screens_and_sticky_on_desktop(): void
    {
        $page = $this->page($this->get(route('legal.terms'))->assertOk());

        $collapsible = $this->find($page, 'details[data-slot="legal-toc-collapsible"]');
        $this->assertContains('lg:hidden', $this->classesOf($collapsible));
        $this->assertSame('Nesta página', $this->text($this->find($collapsible, 'summary')));
        $this->assertSame('Nesta página', $this->find($collapsible, 'nav')->getAttribute('aria-label'));

        $sticky = $this->find($page, 'nav[data-slot="legal-toc"]');
        foreach (['hidden', 'lg:block', 'lg:sticky', 'lg:top-24'] as $class) {
            $this->assertContains($class, $this->classesOf($sticky));
        }
        $this->assertSame('Nesta página', $this->text($this->find($page, '#'.$sticky->getAttribute('aria-labelledby'))));
        $this->assertNull($sticky->querySelector('h2'), 'O rótulo do sumário não entra no outline de títulos.');
    }

    public function test_text_uses_a_reading_column_instead_of_small_print_in_a_card(): void
    {
        $page = $this->page($this->get(route('legal.privacy'))->assertOk());
        $content = $this->find($page, '[data-slot="legal-content"]');
        $classes = $this->classesOf($content);

        foreach (['doc-content', 'max-w-[56ch]', 'text-[1.0625rem]', 'sm:text-lg', 'leading-[1.8]'] as $class) {
            $this->assertContains($class, $classes);
        }
        $this->assertNotContains('text-sm', $classes);
        $this->assertNotContains('card', $classes);
        $this->assertStringStartsWith('Esta política descreve', $this->text($this->find($content, '[data-slot="legal-introduction"]')));
    }

    public function test_privacy_page_points_questions_to_the_privacy_subject_and_to_the_terms(): void
    {
        $page = $this->page($this->get(route('legal.privacy'))->assertOk());
        $content = $this->find($page, '[data-slot="legal-content"]');

        $this->assertNotNull($content->querySelector('a[href="'.route('contact.show', ['assunto' => 'privacy']).'"]'));
        $this->assertNotNull($content->querySelector('a[href="'.route('legal.terms').'"]'));
        $this->assertNotNull($content->querySelector('a[href="mailto:suporte@revisalog.com.br"]'));
    }

    public function test_terms_page_links_the_privacy_policy_and_has_a_meta_description(): void
    {
        $page = $this->page($this->get(route('legal.terms'))->assertOk());

        $this->assertNotNull($this->find($page, '[data-slot="legal-content"]')->querySelector('a[href="'.route('legal.privacy').'"]'));
        $this->assertStringStartsWith('Estes termos regem o uso da plataforma RevisaLog', (string) $this->find($page, 'meta[name="description"]')->getAttribute('content'));
    }

    private function page(TestResponse $response): HTMLDocument
    {
        return HTMLDocument::createFromString((string) $response->getContent(), LIBXML_NOERROR);
    }

    private function find(HTMLDocument|Element $scope, string $selector): Element
    {
        $element = $scope->querySelector($selector);

        $this->assertInstanceOf(Element::class, $element, "Nenhum elemento casa com {$selector}.");

        return $element;
    }

    /**
     * @return list<string>
     */
    private function classesOf(Element $element): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim((string) $element->getAttribute('class'))) ?: []));
    }

    private function text(Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }
}

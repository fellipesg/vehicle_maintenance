<?php

namespace Tests\Feature\Ui\Concerns;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Throwable;

/**
 * Renderiza um trecho Blade com $this->blade() e consulta o HTML por XPath, para os testes dos
 * componentes x-ui.* checarem estrutura, atributos ARIA e classes sem depender de espaços.
 */
trait InspectsUiMarkup
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderUi(string $template, array $data = []): DOMXPath
    {
        return $this->parseHtml((string) $this->blade($template, $data));
    }

    protected function parseHtml(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8"><div id="ui-test-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return new DOMXPath($document);
    }

    protected function uiElement(DOMXPath $xpath, string $query): DOMElement
    {
        $element = $xpath->query($query)->item(0);

        $this->assertInstanceOf(DOMElement::class, $element, "Nenhum elemento em {$query}.");

        return $element;
    }

    protected function uiCount(DOMXPath $xpath, string $query): int
    {
        return $xpath->query($query)->length;
    }

    /**
     * @return list<string>
     */
    protected function uiClasses(DOMElement $element): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($element->getAttribute('class'))) ?: []));
    }

    /**
     * @param  list<string>  $expected
     */
    protected function assertHasClasses(array $expected, DOMElement $element): void
    {
        $classes = $this->uiClasses($element);

        foreach ($expected as $class) {
            $this->assertContains($class, $classes, "Faltou a classe {$class} em <{$element->tagName}>.");
        }
    }

    /**
     * @param  list<string>  $unexpected
     */
    protected function assertLacksClasses(array $unexpected, DOMElement $element): void
    {
        $classes = $this->uiClasses($element);

        foreach ($unexpected as $class) {
            $this->assertNotContains($class, $classes, "A classe {$class} não deveria estar em <{$element->tagName}>.");
        }
    }

    protected function uiText(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }

    /**
     * O componente precisa recusar a combinação de props (em testing, UiProps lança
     * InvalidArgumentException, que a view embrulha em ViewException).
     *
     * @param  array<string, mixed>  $data
     */
    protected function assertUiRejects(string $template, string $messageFragment, array $data = []): void
    {
        try {
            $this->blade($template, $data);
        } catch (Throwable $exception) {
            $cause = $exception;

            while (! $cause instanceof InvalidArgumentException && $cause->getPrevious() !== null) {
                $cause = $cause->getPrevious();
            }

            $this->assertInstanceOf(InvalidArgumentException::class, $cause, 'Esperava InvalidArgumentException, veio '.$exception::class.': '.$exception->getMessage());
            $this->assertStringContainsString($messageFragment, $cause->getMessage());

            return;
        }

        $this->fail("O trecho deveria ter sido recusado: {$template}");
    }
}

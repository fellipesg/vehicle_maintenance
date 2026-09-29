<?php

namespace Tests\Feature\Ui\Concerns;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Renderiza componentes <x-ui.*> e devolve o HTML como documento HTML5 (Dom\HTMLDocument do PHP
 * 8.4), para os testes consultarem com seletor CSS em vez de comparar texto.
 */
trait InspectsRenderedComponents
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderComponent(string $template, array $data = []): HTMLDocument
    {
        return $this->parseHtml((string) $this->blade($template, $data));
    }

    protected function parseHtml(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString('<!doctype html><html lang="pt-BR"><body>'.$html.'</body></html>', LIBXML_NOERROR);
    }

    protected function element(HTMLDocument $document, string $selector): Element
    {
        $element = $document->querySelector($selector);

        $this->assertInstanceOf(Element::class, $element, "Nenhum elemento casa com {$selector}.");

        return $element;
    }

    /**
     * Conteúdo de um <template>, que o parser guarda fora da árvore consultável.
     */
    protected function templateContent(HTMLDocument $document, string $selector): HTMLDocument
    {
        return $this->parseHtml($this->element($document, $selector)->innerHTML);
    }

    /**
     * @return list<string>
     */
    protected function classesOf(Element $element): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim((string) $element->getAttribute('class'))) ?: []));
    }

    /**
     * @return list<string>
     */
    protected function describedByOf(Element $element): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim((string) $element->getAttribute('aria-describedby'))) ?: []));
    }

    /**
     * Simula a volta de um envio com erro: a sessão da requisição traz o old input.
     *
     * @param  array<string, mixed>  $input
     */
    protected function withOldInput(array $input): void
    {
        $session = $this->app['session']->driver();
        $session->put('_old_input', $input);
        $this->app['request']->setLaravelSession($session);
    }
}

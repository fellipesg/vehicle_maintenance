<?php

namespace Tests\Feature\Web\Concerns;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Testing\TestResponse;

/**
 * Abre telas do admin e consulta o HTML por XPath (shell, trilha, H1, menus), sem depender de
 * espaços nem da ordem dos atributos.
 */
trait InspectsAdminPages
{
    protected function adminUser(array $attributes = []): User
    {
        return User::factory()->asUser()->asAdmin()->create($attributes);
    }

    protected function adminPage(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->assertOk()->getContent(), LIBXML_NOERROR);

        return new DOMXPath($document);
    }

    protected function adminElement(DOMXPath $xpath, string $query, ?DOMElement $context = null): DOMElement
    {
        $element = $xpath->query($query, $context)->item(0);

        $this->assertInstanceOf(DOMElement::class, $element, "Nenhum elemento em {$query}.");

        return $element;
    }

    /**
     * @return list<DOMElement>
     */
    protected function adminElements(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
    {
        $elements = [];

        foreach ($xpath->query($query, $context) as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    protected function adminText(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }

    /**
     * Itens da trilha da topbar: rótulo, href (null no item sem link) e se é a página atual.
     *
     * @return list<array{label: string, href: string|null, current: bool}>
     */
    protected function adminTrail(DOMXPath $xpath): array
    {
        $trail = $this->adminElement($xpath, '//header[@data-admin-topbar]//nav[@aria-label="Trilha"]');

        return array_map(function (DOMElement $item) use ($xpath): array {
            $link = $xpath->query('.//a', $item)->item(0);
            $current = $xpath->query('.//*[@aria-current="page"]', $item)->item(0);

            return [
                'label' => $this->adminText($item),
                'href' => $link instanceof DOMElement ? $link->getAttribute('href') : null,
                'current' => $current instanceof DOMElement,
            ];
        }, $this->adminElements($xpath, './/li', $trail));
    }
}

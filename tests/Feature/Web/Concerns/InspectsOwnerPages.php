<?php

namespace Tests\Feature\Web\Concerns;

use App\Models\User;
use App\Models\Vehicle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Testing\TestResponse;

/**
 * Abre as telas do portal do proprietário (renderizadas no servidor) e consulta o HTML por XPath:
 * o único H1, a trilha do page-header, botões e listas, sem depender de espaços nem da ordem dos
 * atributos.
 */
trait InspectsOwnerPages
{
    protected function owner(array $attributes = []): User
    {
        return User::factory()->asUser()->create($attributes)->refresh();
    }

    /**
     * Veículo no nome do dono (pivot com is_current_owner e o tenant dele).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function ownedVehicle(User $owner, array $attributes = []): Vehicle
    {
        $vehicle = Vehicle::factory()->create($attributes);

        $owner->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $owner->tenant_id,
        ]);

        return $vehicle;
    }

    protected function ownerPage(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->assertOk()->getContent(), LIBXML_NOERROR);

        return new DOMXPath($document);
    }

    protected function ownerElement(DOMXPath $xpath, string $query, ?DOMElement $context = null): DOMElement
    {
        $element = $xpath->query($query, $context)->item(0);

        $this->assertInstanceOf(DOMElement::class, $element, "Nenhum elemento em {$query}.");

        return $element;
    }

    /**
     * @return list<DOMElement>
     */
    protected function ownerElements(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
    {
        $elements = [];

        foreach ($xpath->query($query, $context) as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    protected function ownerText(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }

    /**
     * A página tem um único H1 (o do x-ui.page-header), com este texto.
     */
    protected function assertOnlyHeading(DOMXPath $xpath, string $title): void
    {
        $headings = $this->ownerElements($xpath, '//h1');

        $this->assertCount(1, $headings, 'A página precisa de exatamente um <h1>.');
        $this->assertSame('page-header-title', $headings[0]->getAttribute('data-slot'));
        $this->assertSame($title, $this->ownerText($headings[0]));
    }

    /**
     * Itens da trilha do page-header: rótulo e href (null na página atual ou no item sem link).
     *
     * @return list<array{label: string, href: string|null}>
     */
    protected function ownerTrail(DOMXPath $xpath): array
    {
        $trail = $this->ownerElement($xpath, '//main//nav[@data-slot="breadcrumb"]');

        return array_map(function (DOMElement $item) use ($xpath): array {
            $link = $xpath->query('.//a', $item)->item(0);

            return [
                'label' => $this->ownerText($item),
                'href' => $link instanceof DOMElement ? $link->getAttribute('href') : null,
            ];
        }, $this->ownerElements($xpath, './/li', $trail));
    }

    /**
     * Botões e links com o visual de botão (x-ui.button) dentro do <main>, pelo rótulo.
     */
    protected function ownerButton(DOMXPath $xpath, string $label): ?DOMElement
    {
        foreach ($this->ownerElements($xpath, '//main//*[@data-slot="button"]') as $button) {
            $buttonLabel = $xpath->query('.//*[@data-slot="label"]', $button)->item(0);

            if ($buttonLabel instanceof DOMElement && str_starts_with($this->ownerText($buttonLabel), $label)) {
                return $button;
            }
        }

        return null;
    }
}

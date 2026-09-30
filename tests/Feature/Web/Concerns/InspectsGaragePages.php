<?php

namespace Tests\Feature\Web\Concerns;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAccessGrant;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Testing\TestResponse;

/**
 * Estoque do Lojista montado nos testes (dono atual e consignação) e leitura do HTML das telas da
 * garagem por XPath (H1, trilha, links), sem depender de espaços nem da ordem dos atributos.
 */
trait InspectsGaragePages
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function stockVehicle(User $garage, array $attributes = [], ?string $enteredAt = null): Vehicle
    {
        $vehicle = Vehicle::factory()->create($attributes);
        $garage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $garage->tenant_id,
            'created_at' => $enteredAt ?? now(),
            'updated_at' => $enteredAt ?? now(),
        ]);

        return $vehicle;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function consignedStockVehicle(User $garage, string $status, array $attributes = []): Vehicle
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create($attributes);
        $owner->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now()->subYears(2),
            'tenant_id' => $owner->tenant_id,
        ]);
        $garage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $garage->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        VehicleAccessGrant::create([
            'user_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'grant_type' => 'consignment',
            'status' => $status,
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
        ]);

        return $vehicle;
    }

    protected function garagePage(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->assertOk()->getContent(), LIBXML_NOERROR);

        return new DOMXPath($document);
    }

    protected function garageElement(DOMXPath $xpath, string $query, ?DOMElement $context = null): DOMElement
    {
        $element = $xpath->query($query, $context)->item(0);

        $this->assertInstanceOf(DOMElement::class, $element, "Nenhum elemento em {$query}.");

        return $element;
    }

    protected function garageText(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }

    /**
     * O único H1 da página (desenhado pelo x-ui.page-header), com o texto dele.
     */
    protected function assertSingleHeading(DOMXPath $xpath, string $expected): void
    {
        $headings = $xpath->query('//h1');

        $this->assertSame(1, $headings->length, 'A página tem um único <h1>.');
        $this->assertSame($expected, $this->garageText($headings->item(0)));
        $this->assertSame('page-header-title', $headings->item(0)->getAttribute('data-slot'));
    }

    /**
     * Itens da trilha (x-ui.breadcrumb): rótulo e href (null no item atual).
     *
     * @return list<array{0: string, 1: string|null}>
     */
    protected function garageTrail(DOMXPath $xpath): array
    {
        $trail = [];

        foreach ($xpath->query('//main//nav[@aria-label="Trilha"]//li') as $item) {
            $link = $xpath->query('.//a', $item)->item(0);
            $trail[] = [$this->garageText($item), $link instanceof DOMElement ? $link->getAttribute('href') : null];
        }

        return $trail;
    }
}

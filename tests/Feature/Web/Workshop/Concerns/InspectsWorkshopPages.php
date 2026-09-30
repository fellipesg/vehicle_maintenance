<?php

namespace Tests\Feature\Web\Workshop\Concerns;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Testing\TestResponse;

/**
 * Monta os dados do portal da oficina (conta, veículo com proprietário, OS com o Selo da oficina)
 * e consulta o HTML das telas por XPath, sem depender de espaços nem da ordem dos atributos.
 */
trait InspectsWorkshopPages
{
    protected function workshopUser(): User
    {
        return User::factory()->asWorkshop()->create();
    }

    /**
     * Veículo com proprietário vinculado (a OS da oficina precisa de um).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function ownedVehicle(string $plate = 'OFI1C23', array $attributes = []): Vehicle
    {
        $vehicle = Vehicle::factory()->create(array_merge([
            'license_plate' => $plate,
            'brand' => 'Fiat',
            'model' => 'Argo',
            'year' => 2021,
            'color' => 'Prata',
            'current_kilometers' => 40000,
            'odometer_at_registration' => 40000,
        ], $attributes));

        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        return $vehicle;
    }

    /**
     * OS registrada pela oficina da conta (com o Selo da oficina).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function sealedOrder(User $workshopUser, array $attributes = []): Maintenance
    {
        return Maintenance::factory()->sealedByWorkshop()->create(array_merge([
            'workshop_id' => $workshopUser->workshop->id,
            'user_id' => $workshopUser->id,
        ], $attributes))->fresh();
    }

    protected function page(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->assertOk()->getContent(), LIBXML_NOERROR);

        return new DOMXPath($document);
    }

    protected function element(DOMXPath $xpath, string $query, ?DOMElement $context = null): DOMElement
    {
        $element = $xpath->query($query, $context)->item(0);

        $this->assertInstanceOf(DOMElement::class, $element, "Nenhum elemento em {$query}.");

        return $element;
    }

    protected function countNodes(DOMXPath $xpath, string $query, ?DOMElement $context = null): int
    {
        return $xpath->query($query, $context)->length;
    }

    protected function text(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }

    protected function assertSingleH1(DOMXPath $xpath, string $title): void
    {
        $this->assertSame(1, $this->countNodes($xpath, '//h1'), 'A página tem um único H1.');
        $this->assertSame($title, $this->text($this->element($xpath, '//h1')));
    }
}

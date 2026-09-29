<?php

namespace Tests\Feature\Web;

use App\Enums\ServiceCategory;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Registrar manutenção" tem o mesmo padrão nos três portais que registram serviço (Proprietário,
 * Lojista e a Nova OS da oficina): seções numeradas em <x-ui.form-section>, o rótulo "Serviço
 * realizado", a categoria em select e "Revisão obrigatória do fabricante" em checkbox. Proprietário
 * e Lojista mostram o mesmo aviso de procedência (maintenances._declared-notice) com o marcador do
 * próprio portal.
 */
class MaintenanceFormConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['maintenance.auto_verify_linked_workshop' => false]);
    }

    public function test_owner_dealer_and_workshop_forms_share_labels_controls_and_numbered_sections(): void
    {
        $owner = User::factory()->asUser()->create()->refresh();
        $this->attachVehicleToUser($owner, Vehicle::factory()->create());
        $garage = User::factory()->asGarage()->create()->refresh();
        $this->attachVehicleToUser($garage, Vehicle::factory()->create());
        $workshop = User::factory()->asWorkshop()->create();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), Vehicle::factory()->create(['license_plate' => 'OFI1C23']));

        $forms = [
            'Proprietário' => [$this->page($this->actingAs($owner)->get(route('user.maintenances.create'))), '//form[@action="'.route('user.maintenances.store').'"]', 1],
            'Lojista' => [$this->page($this->actingAs($garage)->get(route('garage.maintenances.create'))), '//form[@data-garage-maintenance-form]', 1],
            'Oficina' => [$this->page($this->actingAs($workshop)->get(route('workshop.maintenances.create', ['license_plate' => 'OFI1C23']))), '//form[@data-maintenance-os-form]', 2],
        ];

        foreach ($forms as $portal => [$xpath, $formQuery, $firstNumber]) {
            $form = $this->element($xpath, $formQuery);

            $numbers = array_map(
                fn (DOMElement $marker): int => (int) trim($marker->textContent),
                iterator_to_array($xpath->query('.//fieldset[@data-slot="form-section"]/legend/h2/span[@aria-hidden="true"]', $form)),
            );
            $this->assertNotEmpty($numbers, "{$portal}: seções numeradas.");
            $this->assertSame(range($firstNumber, $firstNumber + count($numbers) - 1), $numbers, "{$portal}: etapas em sequência.");

            $this->assertStringStartsWith('Serviço realizado', $this->text($this->element($xpath, './/label[@for="maintenance_type"]', $form)), "{$portal}: rótulo do serviço.");

            $category = $this->element($xpath, './/select[@name="service_category"]', $form);
            $this->assertSame(count(ServiceCategory::options()), $xpath->query('.//option[@value!=""]', $category)->length, "{$portal}: categorias no select.");
            $this->assertSame(0, $xpath->query('.//input[@type="radio"][@name="service_category"]', $form)->length, "{$portal}: sem radios de categoria.");

            $manufacturer = $this->element($xpath, './/input[@name="is_manufacturer_required"][@type="checkbox"]', $form);
            $this->assertFalse($manufacturer->hasAttribute('role'), "{$portal}: checkbox, não switch.");
        }
    }

    public function test_owner_and_dealer_show_the_same_declared_notice_with_their_marker(): void
    {
        $owner = User::factory()->asUser()->create()->refresh();
        $this->attachVehicleToUser($owner, Vehicle::factory()->create());
        $garage = User::factory()->asGarage()->create()->refresh();
        $this->attachVehicleToUser($garage, Vehicle::factory()->create());

        $expected = [
            'PR' => ['Aparecerá como Declarada pelo proprietário', $this->page($this->actingAs($owner)->get(route('user.maintenances.create')))],
            'LJ' => ['Aparecerá como Declarada pelo lojista', $this->page($this->actingAs($garage)->get(route('garage.maintenances.create')))],
        ];

        foreach ($expected as $marker => [$title, $xpath]) {
            $notice = $this->element($xpath, '//main//*[@data-provenance-notice="declared"]');
            $this->assertStringContainsString('prov-declared', $notice->getAttribute('class'));
            $this->assertSame('note', $notice->getAttribute('role'));
            $this->assertSame($marker, $this->text($this->element($xpath, './/*[contains(@class, "prov-marker--declared")][@aria-hidden="true"]', $notice)));
            $this->assertSame($title, $this->text($this->element($xpath, './/*[@id="aviso-procedencia-titulo"]', $notice)));
            $this->assertStringContainsString('O Selo da oficina só é aplicado quando a oficina da rede registra o serviço no portal dela.', $this->text($notice));
        }
    }

    public function test_owner_edit_keeps_the_notice_saying_the_record_stays_declared(): void
    {
        $owner = User::factory()->asUser()->create()->refresh();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);
        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);

        $xpath = $this->page($this->actingAs($owner)->get(route('user.maintenances.edit', $maintenance)));

        $this->assertSame('Continua como Declarada pelo proprietário', $this->text($this->element($xpath, '//*[@data-provenance-notice="declared"]//*[@id="aviso-procedencia-titulo"]')));
    }

    public function test_auto_verify_environment_uses_the_same_notice_in_both_portals(): void
    {
        config(['maintenance.auto_verify_linked_workshop' => true]);
        $owner = User::factory()->asUser()->create()->refresh();
        $this->attachVehicleToUser($owner, Vehicle::factory()->create());
        $garage = User::factory()->asGarage()->create()->refresh();
        $this->attachVehicleToUser($garage, Vehicle::factory()->create());

        foreach ([
            'Declarada pelo proprietário' => $this->actingAs($owner)->get(route('user.maintenances.create')),
            'Declarada pelo lojista' => $this->actingAs($garage)->get(route('garage.maintenances.create')),
        ] as $label => $response) {
            $notice = $this->element($this->page($response), '//*[@data-provenance-notice="auto-verify"]');
            $this->assertStringContainsString('Selo da oficina neste ambiente de testes', $this->text($notice));
            $this->assertStringContainsString('Em produção a manutenção aparece como '.$label.'.', $this->text($notice));
        }
    }

    private function page(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->assertOk()->getContent(), LIBXML_NOERROR);

        return new DOMXPath($document);
    }

    private function element(DOMXPath $xpath, string $query, ?DOMElement $context = null): DOMElement
    {
        $element = $xpath->query($query, $context)->item(0);

        $this->assertInstanceOf(DOMElement::class, $element, "Nenhum elemento em {$query}.");

        return $element;
    }

    private function text(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }
}

<?php

namespace Tests\Feature\Components;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenancePhoto;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-provenance-card>: data do serviço, veículo, km e categoria, com a procedência em texto e a
 * evidência no lugar de "não verificada" (USR-10, GAR-16, PUB-X01).
 */
class ProvenanceCardComponentTest extends TestCase
{
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_card_shows_service_date_vehicle_km_category_and_provenance(): void
    {
        $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23']);
        $workshop = Workshop::factory()->create(['name' => 'Silva Auto Center']);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'maintenance_type' => 'Troca de correia dentada',
            'maintenance_date' => '2024-05-10',
            'kilometers' => 42000,
            'service_category' => 'mechanical',
        ]);
        $maintenance->forceFill(['verified_workshop_id' => $workshop->id, 'verification_code' => 'RVL-ABCD-EF'])->saveQuietly();

        $xpath = $this->renderUi('<x-provenance-card :maintenance="$maintenance" show-vehicle href="/usuario/manutencoes/1" as="li" heading-level="h3" />', [
            'maintenance' => $maintenance->fresh()->load(['vehicle', 'workshop', 'verifiedWorkshop', 'user']),
        ]);

        $card = $this->uiElement($xpath, '//li[@data-maintenance-card]');
        $this->assertSame('manutencao-'.$maintenance->id, $card->getAttribute('id'));
        $this->assertSame('1', $card->getAttribute('data-verified'));
        $this->assertHasClasses(['prov-card', 'prov-verified', 'relative'], $card);
        $this->assertLacksClasses(['mb-3'], $card);

        $this->assertSame('Troca de correia dentada', $this->uiText($this->uiElement($xpath, '//h3/a[@href="/usuario/manutencoes/1"]')));
        $this->assertSame(1, $this->uiCount($xpath, '//li//a'), 'Um link só: o título, esticado sobre o card.');
        $this->assertSame('2024-05-10', $this->uiElement($xpath, '//time')->getAttribute('datetime'));
        $this->assertSame('10/05/2024', $this->uiText($this->uiElement($xpath, '//time')));

        $details = $this->uiText($this->uiElement($xpath, '//p[@data-slot="provenance-card-details"]'));
        $this->assertStringContainsString('Honda Civic', $details);
        $this->assertStringContainsString('CIV1C23', $details);
        $this->assertStringContainsString('42.000 km', $details);
        $this->assertStringContainsString('Mecânica', $details);

        $this->assertSame('Selo da oficina · Silva Auto Center', $this->uiText($this->uiElement($xpath, '//p[@data-slot="provenance-card-label"]')));
        $this->assertSame('Código RVL-ABCD-EF', $this->uiText($this->uiElement($xpath, '//p[@data-slot="provenance-card-evidence"]')));
        $this->assertStringNotContainsString('não verificada', $this->html($xpath));
        $this->assertSame('true', $this->uiElement($xpath, '//div[contains(@class, "prov-marker")]')->getAttribute('aria-hidden'));
    }

    public function test_declared_card_lists_evidence_instead_of_not_verified(): void
    {
        $maintenance = Maintenance::factory()->declaredByOwner()->create(['maintenance_type' => 'Revisão declarada']);
        Invoice::factory()->create(['maintenance_id' => $maintenance->id]);
        MaintenancePhoto::factory()->count(3)->create(['maintenance_id' => $maintenance->id]);

        $xpath = $this->renderUi('<x-provenance-card :maintenance="$maintenance" />', [
            'maintenance' => $maintenance->fresh()->loadCount(['invoices', 'photos']),
        ]);

        $card = $this->uiElement($xpath, '//div[@data-maintenance-card]');
        $this->assertHasClasses(['prov-card', 'prov-card--declared', 'prov-declared', 'mb-3'], $card);
        $this->assertSame('0', $card->getAttribute('data-verified'));
        $this->assertSame('Declarada pelo proprietário', $this->uiText($this->uiElement($xpath, '//p[@data-slot="provenance-card-label"]')));
        $this->assertSame('NF-e anexada · 3 fotos', $this->uiText($this->uiElement($xpath, '//p[@data-slot="provenance-card-evidence"]')));
        $this->assertSame(0, $this->uiCount($xpath, '//a'));
        $this->assertSame(0, $this->uiCount($xpath, '//p[@data-slot="provenance-card-details"]//span[contains(., "Placa")]'), 'Sem show-vehicle, sem veículo.');
    }

    public function test_declared_card_without_evidence_says_so(): void
    {
        $maintenance = Maintenance::factory()->declaredByGarage()->create();

        $xpath = $this->renderUi('<x-provenance-card :maintenance="$maintenance" :anchor="false" />', ['maintenance' => $maintenance->fresh()]);

        $this->assertSame('Sem nota fiscal nem fotos', $this->uiText($this->uiElement($xpath, '//p[@data-slot="provenance-card-evidence"]')));
        $this->assertSame('Declarada pelo lojista', $this->uiText($this->uiElement($xpath, '//p[@data-slot="provenance-card-label"]')));
        $this->assertFalse($this->uiElement($xpath, '//div[@data-maintenance-card]')->hasAttribute('id'));
    }

    public function test_expandable_card_shows_items_and_after_photos_that_were_loaded(): void
    {
        $maintenance = Maintenance::factory()->declaredByOwner()->create();
        MaintenanceItem::factory()->create(['maintenance_id' => $maintenance->id, 'name' => 'Filtro de óleo', 'quantity' => 2]);
        MaintenancePhoto::factory()->create(['maintenance_id' => $maintenance->id]);

        $xpath = $this->renderUi('<x-provenance-card :maintenance="$maintenance" expandable />', [
            'maintenance' => $maintenance->fresh()->load(['items', 'photos']),
        ]);

        $details = $this->uiElement($xpath, '//details[@data-slot="provenance-card-expand"]');
        $this->assertStringContainsString('Ver serviços (1)', $this->uiText($this->uiElement($xpath, '//summary')));
        $this->assertStringContainsString('Filtro de óleo', $this->uiText($details));
        $this->assertStringContainsString('2x', $this->uiText($details));
        $image = $this->uiElement($xpath, '//details//img');
        $this->assertStringContainsString('object-contain', $image->getAttribute('class'));
        $this->assertSame('Foto do serviço 1', $image->getAttribute('alt'));

        $withLink = $this->renderUi('<x-provenance-card :maintenance="$maintenance" expandable href="/x" />', [
            'maintenance' => $maintenance->fresh()->load(['items', 'photos']),
        ]);
        $this->assertSame(0, $this->uiCount($withLink, '//details'), 'Com página de detalhe, o card é o link.');
    }

    public function test_card_does_not_lazy_load_the_vehicle_in_a_list(): void
    {
        $maintenances = Maintenance::factory()->count(2)->declaredByOwner()->create();
        $list = Maintenance::query()->with(['workshop', 'verifiedWorkshop', 'user'])->whereKey($maintenances->modelKeys())->get();

        $html = (string) $this->blade('@foreach($list as $maintenance)<x-provenance-card :maintenance="$maintenance" show-vehicle />@endforeach', ['list' => $list]);

        $this->assertSame(2, substr_count($html, 'data-maintenance-card='));
    }

    private function html(\DOMXPath $xpath): string
    {
        return (string) $xpath->document->saveHTML();
    }

    /**
     * LIVE-07: na declarada, a linha de detalhes diz qual oficina fez o serviço (a cadastrada que o
     * registro cita ou o nome digitado). Com Selo da oficina, a oficina já está no rótulo.
     */
    public function test_declared_card_names_the_workshop_the_declarer_informed(): void
    {
        $workshop = Workshop::factory()->create(['name' => 'Auto Center Paulista']);
        $cited = Maintenance::factory()->declaredByOwner()->create(['workshop_id' => $workshop->id, 'workshop_name' => null]);
        $typed = Maintenance::factory()->declaredByGarage()->create(['workshop_id' => null, 'workshop_name' => 'Oficina do Zé']);
        $none = Maintenance::factory()->declaredByOwner()->create(['workshop_id' => null, 'workshop_name' => null]);

        $citedCard = $this->renderUi('<x-provenance-card :maintenance="$maintenance" />', ['maintenance' => $cited->fresh()->load('workshop')]);
        $this->assertSame('Oficina: Auto Center Paulista', $this->uiText($this->uiElement($citedCard, '//span[@data-slot="provenance-card-workshop"]')));
        $this->assertSame('true', $this->uiElement($citedCard, '//span[@data-slot="provenance-card-workshop"]/svg')->getAttribute('aria-hidden'));

        $typedCard = $this->renderUi('<x-provenance-card :maintenance="$maintenance" />', ['maintenance' => $typed->fresh()->load('workshop')]);
        $this->assertSame('Oficina: Oficina do Zé', $this->uiText($this->uiElement($typedCard, '//span[@data-slot="provenance-card-workshop"]')));

        $this->assertSame(0, $this->uiCount($this->renderUi('<x-provenance-card :maintenance="$maintenance" />', ['maintenance' => $none->fresh()->load('workshop')]), '//span[@data-slot="provenance-card-workshop"]'));
        $this->assertSame(0, $this->uiCount($this->renderUi('<x-provenance-card :maintenance="$maintenance" :show-workshop="false" />', ['maintenance' => $cited->fresh()->load('workshop')]), '//span[@data-slot="provenance-card-workshop"]'));

        $sealed = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $workshop->id]);
        $this->assertSame(0, $this->uiCount($this->renderUi('<x-provenance-card :maintenance="$maintenance" />', ['maintenance' => $sealed->fresh()->load(['workshop', 'verifiedWorkshop'])]), '//span[@data-slot="provenance-card-workshop"]'), 'Com selo, a oficina fica no rótulo.');
    }
}

<?php

namespace Tests\Feature\Components;

use App\Enums\Portal;
use App\Enums\WarrantyScope;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenancePhoto;
use App\Models\MaintenanceWarranty;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * maintenances/_detail: um só corpo de detalhe de manutenção para proprietário, lojista e oficina
 * (GAR-04), com a garantia do serviço.
 */
class MaintenanceDetailPartialTest extends TestCase
{
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_detail_shows_seal_service_data_warranty_items_invoices_and_photos(): void
    {
        $vehicle = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Corolla', 'license_plate' => 'COR0L23']);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão 40 mil',
            'maintenance_date' => now()->subDays(10)->toDateString(),
            'kilometers' => 40100,
            'service_category' => 'electrical',
            'description' => "Troca de velas\nAjuste de freio",
            'is_manufacturer_required' => true,
        ]);
        $item = MaintenanceItem::factory()->create(['maintenance_id' => $maintenance->id, 'name' => 'Vela de ignição', 'quantity' => 4, 'unit_price' => 50, 'total_price' => 200, 'part_number' => 'NGK-123']);
        MaintenanceItem::factory()->create(['maintenance_id' => $maintenance->id, 'name' => 'Mão de obra', 'quantity' => 1, 'unit_price' => 150, 'total_price' => 150]);
        MaintenanceWarranty::factory()->create([
            'maintenance_id' => $maintenance->id,
            'scope' => WarrantyScope::Order,
            'name' => 'Garantia de serviço 90 dias',
            'body' => 'Cobre defeitos de montagem.',
            'duration_days' => 90,
            'starts_at' => now()->subDays(10)->toDateString(),
            'ends_at' => now()->addDays(80)->toDateString(),
        ]);
        MaintenanceWarranty::factory()->create([
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => $item->id,
            'scope' => WarrantyScope::Item,
            'duration_days' => 365,
            'starts_at' => now()->subDays(10)->toDateString(),
            'ends_at' => now()->addDays(355)->toDateString(),
        ]);
        Invoice::factory()->create(['maintenance_id' => $maintenance->id, 'file_name' => 'nfe-123.xml', 'file_path' => 'invoices/nfe-123.xml']);
        MaintenancePhoto::factory()->create(['maintenance_id' => $maintenance->id, 'subject' => MaintenancePhoto::SUBJECT_VEHICLE, 'stage' => MaintenancePhoto::STAGE_AFTER]);

        $html = view('maintenances._detail', ['maintenance' => $maintenance->fresh(), 'portal' => Portal::Owner])->render();
        $xpath = $this->parseHtml($html);

        $this->assertSame(0, $this->uiCount($xpath, '//h1'), 'O H1 é do page-header da página.');
        $this->assertSame(1, $this->uiCount($xpath, '//div[@data-slot="provenance-seal"]'));
        $this->assertStringContainsString('Selo da oficina', $html);

        $details = $this->uiText($this->uiElement($xpath, '//section[.//h2[normalize-space()="Detalhes do serviço"]]'));
        $this->assertStringContainsString('Toyota Corolla', $details);
        $this->assertStringContainsString('COR0L23', $details);
        $this->assertStringContainsString('40.100 km', $details);
        $this->assertStringContainsString('Elétrica', $details);
        $this->assertStringContainsString('Revisão obrigatória do fabricante Sim', $details);
        $this->assertSame(route('user.vehicles.show', $vehicle), $this->uiElement($xpath, '//section[.//h2[normalize-space()="Detalhes do serviço"]]//a')->getAttribute('href'));

        $warranty = $this->uiText($this->uiElement($xpath, '//section[@data-slot="maintenance-warranty"]'));
        $this->assertStringContainsString('Em garantia', $warranty);
        $this->assertStringContainsString('Válida até '.now()->addDays(80)->format('d/m/Y'), $warranty);
        $this->assertStringContainsString('90 dias', $warranty);
        $this->assertStringContainsString('Cobre defeitos de montagem.', $warranty);
        $this->assertStringContainsString('1 peça tem garantia própria', $warranty);

        $table = $this->uiElement($xpath, '//section[@data-slot="maintenance-items"]//div[@data-slot="table"]');
        $this->assertSame('md', $table->getAttribute('data-stack'));
        $this->assertSame(2, $this->uiCount($xpath, '//section[@data-slot="maintenance-items"]//tbody/tr'));
        $this->assertSame('row', $this->uiElement($xpath, '//tbody/tr[1]/th')->getAttribute('scope'));
        $this->assertStringContainsString('Código da peça: NGK-123', $this->uiText($this->uiElement($xpath, '//tbody/tr[1]/th')));
        $this->assertStringContainsString('Em garantia', $this->uiText($this->uiElement($xpath, '//tbody/tr[1]/td[@data-label="Garantia"]')));
        $this->assertSame('R$ 350,00', $this->uiText($this->uiElement($xpath, '//tfoot//td')));

        $invoice = $this->uiText($this->uiElement($xpath, '//section[@data-slot="maintenance-invoices"]'));
        $this->assertStringContainsString('Notas fiscais (1)', $invoice);
        $this->assertStringContainsString('XML', $invoice);
        $this->assertStringContainsString('(abre em nova aba)', $invoice);

        $photo = $this->uiElement($xpath, '//section[@data-slot="maintenance-photos"]//img');
        $this->assertSame('Foto 1 de 1 — Revisão 40 mil, '.now()->subDays(10)->format('d/m/Y').'. Carro depois do serviço', $photo->getAttribute('alt'));
        $this->assertStringContainsString('object-contain', $photo->getAttribute('class'));
    }

    /**
     * USR-33 / WRK-24: antes e depois do mesmo assunto lado a lado (md:grid-cols-2, "durante" na linha
     * toda) e a <x-ui.lightbox>: cada miniatura continua um link para a imagem (sem JS abre em nova
     * aba) e abre o carrossel na foto clicada, com alt contextual "Foto 2 de 5 — serviço, data. grupo",
     * "Foto 1 de N" e as setas.
     */
    public function test_photos_pair_before_and_after_and_open_in_the_lightbox(): void
    {
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'maintenance_type' => 'Troca de pastilhas',
            'maintenance_date' => '2026-03-12',
        ]);
        $photo = fn (string $subject, string $stage, string $path) => MaintenancePhoto::factory()->create([
            'maintenance_id' => $maintenance->id,
            'subject' => $subject,
            'stage' => $stage,
            'path' => $path,
        ]);
        $photo(MaintenancePhoto::SUBJECT_VEHICLE, MaintenancePhoto::STAGE_AFTER, 'maintenance-photos/carro-depois.jpg');
        $photo(MaintenancePhoto::SUBJECT_VEHICLE, MaintenancePhoto::STAGE_BEFORE, 'maintenance-photos/carro-antes.jpg');
        $photo(MaintenancePhoto::SUBJECT_VEHICLE, MaintenancePhoto::STAGE_DURING, 'maintenance-photos/carro-durante.jpg');
        $photo(MaintenancePhoto::SUBJECT_PART, MaintenancePhoto::STAGE_BEFORE, 'maintenance-photos/peca-antes-1.jpg');
        $photo(MaintenancePhoto::SUBJECT_PART, MaintenancePhoto::STAGE_BEFORE, 'maintenance-photos/peca-antes-2.jpg');

        $xpath = $this->parseHtml(view('maintenances._detail', ['maintenance' => $maintenance->fresh(), 'portal' => Portal::Workshop])->render());

        $viewerId = 'fotos-manutencao-'.$maintenance->id;
        $grid = $this->uiElement($xpath, '//section[@data-slot="maintenance-photos"]//div[@data-photo-gallery]');
        $this->assertSame($viewerId, $grid->getAttribute('data-photo-gallery'));
        $this->assertStringContainsString('md:grid-cols-2', $grid->getAttribute('class'));
        $this->assertSame('Fotos (5)', $this->uiText($this->uiElement($xpath, '//section[@data-slot="maintenance-photos"]//h2')));

        $groups = array_map(fn ($group) => $group->getAttribute('data-photo-group'), iterator_to_array($xpath->query('//div[@data-photo-group]')));
        $this->assertSame(['vehicle_before', 'vehicle_after', 'vehicle_during', 'part_before'], $groups, 'Antes e depois lado a lado; durante depois.');
        $this->assertStringContainsString('md:col-span-2', $this->uiElement($xpath, '//div[@data-photo-group="vehicle_during"]')->getAttribute('class'));
        $this->assertStringNotContainsString('md:col-span-2', $this->uiElement($xpath, '//div[@data-photo-group="vehicle_before"]')->getAttribute('class'));

        $items = $xpath->query('//section[@data-slot="maintenance-photos"]//a[@data-lightbox-open]');
        $this->assertSame(5, $items->length);
        $this->assertSame($viewerId, $items->item(0)->getAttribute('data-lightbox-open'));
        $this->assertSame(['0', '1', '2', '3', '4'], array_map(fn ($item) => $item->getAttribute('data-lightbox-index'), iterator_to_array($items)));
        $this->assertSame('_blank', $items->item(0)->getAttribute('target'));
        $this->assertStringContainsString('carro-antes.jpg', $items->item(0)->getAttribute('href'));
        $this->assertSame('(abre em nova aba)', $this->uiText($this->uiElement($xpath, '//a[@data-lightbox-index="0"]//*[@data-lightbox-new-tab-hint]')));

        $thumbnail = $this->uiElement($xpath, '//a[@data-lightbox-index="0"]//img');
        $this->assertSame('Foto 1 de 5 — Troca de pastilhas, 12/03/2026. Carro antes do serviço', $thumbnail->getAttribute('alt'));
        $this->assertSame('lazy', $thumbnail->getAttribute('loading'));
        $this->assertStringContainsString('object-contain', $thumbnail->getAttribute('class'));
        $this->assertStringNotContainsString('object-cover', $thumbnail->getAttribute('class'), 'A miniatura não corta a foto.');
        $this->assertSame('Foto 5 de 5 — Troca de pastilhas, 12/03/2026. Peças ao retirar', $this->uiElement($xpath, '//a[@data-lightbox-index="4"]//img')->getAttribute('alt'));

        $dialog = $this->uiElement($xpath, '//dialog[@id="'.$viewerId.'"]');
        $this->assertTrue($dialog->hasAttribute('data-ui-lightbox'));
        $this->assertTrue($dialog->hasAttribute('data-ui-dialog'), 'O motor do diálogo é o de resources/js/ui/dialog.js.');
        $this->assertSame('Fotos do serviço', $this->uiText($this->uiElement($xpath, '//dialog[@id="'.$viewerId.'"]//h2')));
        $this->assertSame('Foto 1 de 5', $this->uiText($this->uiElement($xpath, '//dialog//*[@data-lightbox-counter]')));
        $this->assertSame('polite', $this->uiElement($xpath, '//dialog//*[@data-lightbox-status]')->getAttribute('aria-live'));

        $slides = $xpath->query('//dialog//*[@data-lightbox-slide]');
        $this->assertSame(5, $slides->length);
        $this->assertSame('slide', $slides->item(1)->getAttribute('aria-roledescription'));
        $this->assertSame('Foto 2 de 5', $slides->item(1)->getAttribute('aria-label'));
        $this->assertSame('Foto 2 de 5 — Troca de pastilhas, 12/03/2026. Carro depois do serviço', $this->uiElement($xpath, '//dialog//*[@data-lightbox-slide][2]//img')->getAttribute('alt'));
        $this->assertStringContainsString('object-contain', $this->uiElement($xpath, '//dialog//*[@data-lightbox-slide][2]//img')->getAttribute('class'));

        $this->assertSame('Foto anterior', $this->uiElement($xpath, '//dialog//button[@data-lightbox-prev]')->getAttribute('aria-label'));
        $this->assertTrue($this->uiElement($xpath, '//dialog//button[@data-lightbox-next]')->hasAttribute('data-dialog-initial-focus'));
        $this->assertStringContainsString('(abre em nova aba)', $this->uiText($this->uiElement($xpath, '//dialog//a[@data-lightbox-original]')));

        $script = file_get_contents(resource_path('js/ui/lightbox.js'));
        $this->assertStringContainsString("import { openDialog } from './dialog';", $script);
        $this->assertStringContainsString('dataset.lightboxReady', $script, 'Idempotente.');
        $this->assertStringContainsString('`Foto ${current + 1} de ${total}`', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
    }

    public function test_single_photo_lightbox_has_no_arrows(): void
    {
        $maintenance = Maintenance::factory()->declaredByOwner()->create();
        MaintenancePhoto::factory()->create(['maintenance_id' => $maintenance->id, 'subject' => MaintenancePhoto::SUBJECT_PART, 'stage' => MaintenancePhoto::STAGE_AFTER]);

        $xpath = $this->parseHtml(view('maintenances._detail', ['maintenance' => $maintenance->fresh(), 'portal' => Portal::Owner])->render());

        $this->assertSame('Foto 1 de 1', $this->uiText($this->uiElement($xpath, '//dialog//*[@data-lightbox-counter]')));
        $this->assertSame(0, $this->uiCount($xpath, '//dialog//*[@data-lightbox-prev or @data-lightbox-next]'));
        $this->assertTrue($this->uiElement($xpath, '//dialog//button[@data-dialog-close-button]')->hasAttribute('data-dialog-initial-focus'));
    }

    public function test_declared_maintenance_without_warranty_items_or_attachments(): void
    {
        $maintenance = Maintenance::factory()->declaredByOwner()->create(['service_category' => 'unknown', 'kilometers' => null]);

        $html = view('maintenances._detail', ['maintenance' => $maintenance->fresh(), 'portal' => 'user', 'vehicleUrl' => false])->render();
        $xpath = $this->parseHtml($html);

        $this->assertStringContainsString('Declarada pelo proprietário', $html);
        $this->assertStringContainsString('Nenhuma garantia do serviço registrada.', $html);
        $this->assertSame('Nenhum item registrado', $this->uiText($this->uiElement($xpath, '//tr[@data-slot="table-empty"]//*[@data-slot="empty-state-title"]')));
        $this->assertSame(0, $this->uiCount($xpath, '//section[@data-slot="maintenance-invoices"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//section[@data-slot="maintenance-photos"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//section[.//h2[normalize-space()="Detalhes do serviço"]]//a'), 'vehicleUrl false: sem link.');
        $this->assertStringContainsString('Não informada', $html);
    }

    public function test_expired_service_warranty_is_marked_as_ended(): void
    {
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['maintenance_date' => now()->subYear()->toDateString()]);
        MaintenanceWarranty::factory()->create([
            'maintenance_id' => $maintenance->id,
            'scope' => WarrantyScope::Order,
            'starts_at' => now()->subYear()->toDateString(),
            'ends_at' => now()->subMonths(9)->toDateString(),
        ]);

        $html = view('maintenances._detail', ['maintenance' => $maintenance->fresh(), 'portal' => Portal::Dealer])->render();

        $this->assertStringContainsString('Garantia encerrada', $html);
    }
}

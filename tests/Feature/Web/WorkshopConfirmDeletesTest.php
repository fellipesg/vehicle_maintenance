<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Exclusões do portal da oficina (OS e modelo de garantia) passam pelo <x-ui.confirm-dialog>, com o
 * texto da consequência, e não pelo confirm() nativo.
 */
class WorkshopConfirmDeletesTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_views_do_not_use_the_native_confirm(): void
    {
        foreach (File::allFiles(resource_path('views/workshop')) as $file) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\w.$-])confirm\(/', $file->getContents(), $file->getRelativePathname());
            $this->assertStringNotContainsString('onsubmit=', $file->getContents(), $file->getRelativePathname());
        }
    }

    public function test_os_deletion_asks_with_the_app_dialog(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'QOS6H54']);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $user->workshop->id, 'vehicle_id' => $vehicle->id]);
        $code = $maintenance->fresh()->verification_code;

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.show', $maintenance)));
        $form = $this->deleteForm($xpath, route('workshop.maintenances.destroy', $maintenance));
        // "Excluir OS" fica no menu "Mais ações": o data-confirm vai no botão de envio do item.
        $button = $xpath->query('.//button[@type="submit"]', $form)->item(0);

        $this->assertSame('Excluir a OS de QOS6H54?', $button->getAttribute('data-confirm-title'));
        $this->assertSame('A OS sai do histórico do veículo QOS6H54. O código '.$code.' deixa de valer e o proprietário perde este registro com Selo da oficina. Não é possível desfazer.', $button->getAttribute('data-confirm'));
        $this->assertSame('Excluir OS', $button->getAttribute('data-confirm-action-label'));
        $this->assertSame('danger', $button->getAttribute('data-confirm-variant'));
        $this->assertFalse($form->hasAttribute('onsubmit'));
        $this->assertSame(1, $xpath->query('//dialog[@data-ui-confirm-dialog]')->length);
    }

    public function test_warranty_template_deletion_asks_with_the_app_dialog(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($user->workshop)->create(['name' => 'Garantia de freios']);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.warranty-templates.index')));
        $form = $this->deleteForm($xpath, route('workshop.warranty-templates.destroy', $template));

        $this->assertSame('Excluir o modelo de garantia?', $form->getAttribute('data-confirm-title'));
        $this->assertStringContainsString('Garantia de freios', $form->getAttribute('data-confirm'));
        $this->assertSame('Excluir modelo', $form->getAttribute('data-confirm-action-label'));
        $this->assertSame('danger', $form->getAttribute('data-confirm-variant'));
        $this->assertStringContainsString('Excluir o modelo Garantia de freios', trim(preg_replace('/\s+/', ' ', $form->textContent)));
    }

    public function test_removing_an_item_with_warranty_asks_with_the_app_dialog(): void
    {
        $script = file_get_contents(resource_path('js/maintenance-items.js'));

        $this->assertStringContainsString("import { confirmAction } from './ui/confirm';", $script);
        $this->assertMatchesRegularExpression("/await confirmAction\\(\\{[^}]*variant: 'danger'/s", $script);
        $this->assertStringNotContainsString('window.confirm', $script);
    }

    private function page(TestResponse $response): DOMXPath
    {
        $response->assertOk();

        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function deleteForm(DOMXPath $xpath, string $action): DOMElement
    {
        $forms = $xpath->query('//form[@action="'.$action.'"][.//input[@name="_method"][@value="DELETE"]]');

        $this->assertSame(1, $forms->length, "Formulário de exclusão para {$action}.");

        return $forms->item(0);
    }
}

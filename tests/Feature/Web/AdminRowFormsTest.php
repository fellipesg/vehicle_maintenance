<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\Admin\BlogCategoryController;
use App\Http\Controllers\Web\Admin\VehicleModelController;
use App\Models\BlogCategory;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Modelos da marca e categorias do blog são criados e editados em diálogos, cada formulário com o
 * seu error bag: o erro e o texto digitado voltam só no diálogo enviado, que reabre na carga, e o
 * aviso no topo oferece "Corrigir".
 */
class AdminRowFormsTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_failed_model_update_shows_the_error_only_in_its_dialog(): void
    {
        $brand = VehicleBrand::factory()->create();
        $edited = VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id, 'name' => 'Uno']);
        $other = VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id, 'name' => 'Palio']);

        $response = $this->actingAs($this->adminUser())
            ->from(route('admin.brands.show', $brand))
            ->put(route('admin.models.update', $edited), ['name' => 'Palio']);

        $response->assertRedirect(route('admin.brands.show', $brand))
            ->assertSessionHasErrors(['name'], null, VehicleModelController::updateBag($edited));
        $this->assertSame('Uno', $edited->fresh()->name);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.show', $brand)));

        $editedDialog = $this->adminElement($xpath, '//dialog[@id="modelo-'.$edited->id.'-dialogo"]');
        $this->assertSame('true', $editedDialog->getAttribute('data-dialog-open-on-load'));
        $editedInput = $this->adminElement($xpath, './/input[@id="modelo-'.$edited->id.'-nome"]', $editedDialog);
        $this->assertSame('true', $editedInput->getAttribute('aria-invalid'));
        $this->assertSame('Palio', $editedInput->getAttribute('value'));
        $this->assertStringContainsString('modelo-'.$edited->id.'-nome-error', $editedInput->getAttribute('aria-describedby'));

        $otherDialog = $this->adminElement($xpath, '//dialog[@id="modelo-'.$other->id.'-dialogo"]');
        $this->assertFalse($otherDialog->hasAttribute('data-dialog-open-on-load'));
        $otherInput = $this->adminElement($xpath, './/input[@id="modelo-'.$other->id.'-nome"]', $otherDialog);
        $this->assertFalse($otherInput->hasAttribute('aria-invalid'));
        $this->assertSame('Palio', $otherInput->getAttribute('value'));

        $newInput = $this->adminElement($xpath, '//input[@id="novo-modelo-nome"]');
        $this->assertFalse($newInput->hasAttribute('aria-invalid'));
        $this->assertSame('', $newInput->getAttribute('value'));

        $alert = $this->adminElement($xpath, '//*[@data-admin-dialog-error]');
        $this->assertStringContainsString('O modelo Uno não foi salvo.', $this->adminText($alert));
        $this->assertSame('modelo-'.$edited->id.'-dialogo', $this->adminElement($xpath, './/button[@data-dialog-open]', $alert)->getAttribute('data-dialog-open'));
    }

    public function test_failed_model_creation_keeps_the_typed_name_in_the_new_model_dialog(): void
    {
        $brand = VehicleBrand::factory()->create();
        $existing = VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id, 'name' => 'Toro']);

        $this->actingAs($this->adminUser())
            ->from(route('admin.brands.show', $brand))
            ->post(route('admin.brands.models.store', $brand), ['name' => 'Toro', 'is_active' => '0'])
            ->assertSessionHasErrors(['name'], null, VehicleModelController::CREATE_BAG);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.show', $brand)));

        $dialog = $this->adminElement($xpath, '//dialog[@id="novo-modelo"]');
        $this->assertSame('true', $dialog->getAttribute('data-dialog-open-on-load'));
        $newInput = $this->adminElement($xpath, './/input[@id="novo-modelo-nome"]', $dialog);
        $this->assertSame('Toro', $newInput->getAttribute('value'));
        $this->assertSame('true', $newInput->getAttribute('aria-invalid'));
        $this->assertFalse($this->adminElement($xpath, './/input[@id="novo-modelo-ativo"]', $dialog)->hasAttribute('checked'));

        $rowInput = $this->adminElement($xpath, '//input[@id="modelo-'.$existing->id.'-nome"]');
        $this->assertFalse($rowInput->hasAttribute('aria-invalid'));
        $this->assertSame('Toro', $rowInput->getAttribute('value'));
        $this->assertTrue($this->adminElement($xpath, '//input[@id="modelo-'.$existing->id.'-ativo"]')->hasAttribute('checked'));
    }

    public function test_model_update_still_saves_and_unchecked_means_inactive(): void
    {
        $brand = VehicleBrand::factory()->create();
        $model = VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id, 'name' => 'Ka', 'is_active' => true]);

        $this->actingAs($this->adminUser())
            ->put(route('admin.models.update', $model), ['name' => 'Ka Sedan', 'is_active' => '0'])
            ->assertRedirect(route('admin.brands.show', $brand))
            ->assertSessionHas('success', 'Modelo atualizado.');

        $this->assertSame('Ka Sedan', $model->fresh()->name);
        $this->assertFalse($model->fresh()->is_active);
    }

    public function test_models_table_is_read_only_with_actions_in_the_row_menu(): void
    {
        $brand = VehicleBrand::factory()->create(['name' => 'Fiat']);
        $model = VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id, 'name' => 'Mobi', 'is_active' => false]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.show', $brand)));

        $row = $this->adminElement($xpath, '//tbody/tr[th[@scope="row"][normalize-space()="Mobi"]]');
        $this->assertSame(0, $xpath->query('.//input[not(@type="hidden")]', $row)->length, 'A linha não é mais um formulário sempre editável.');
        $this->assertSame('Inativo', $this->adminText($this->adminElement($xpath, './/*[@data-slot="badge"]', $row)));
        $edit = $this->adminElement($xpath, './/*[@role="menuitem"][@data-dialog-open="modelo-'.$model->id.'-dialogo"]', $row);
        $this->assertSame('Editar modelo', $this->adminText($edit));

        $this->assertSame('novo-modelo', $this->adminElement($xpath, '//header[@data-slot="page-header"]//button[contains(., "Novo modelo")]')->getAttribute('data-dialog-open'));
        $this->assertStringStartsWith('Nome do modelo', $this->adminText($this->adminElement($xpath, '//label[@for="modelo-'.$model->id.'-nome"]')));
    }

    public function test_model_filter_narrows_the_table(): void
    {
        $brand = VehicleBrand::factory()->create();
        VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id, 'name' => 'Onix Plus']);
        VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id, 'name' => 'Tracker']);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.show', [$brand, 'q' => 'onix'])));

        $this->assertSame(['Onix Plus'], array_map(fn ($cell): string => $this->adminText($cell), $this->adminElements($xpath, '//tbody/tr/th[@scope="row"]')));
        $this->assertStringContainsString('2 modelos cadastrados', $this->adminText($this->adminElement($xpath, '//header[@data-slot="page-header"]')));
    }

    public function test_failed_category_update_shows_the_error_only_in_its_dialog(): void
    {
        $edited = BlogCategory::factory()->create(['name' => 'Dicas']);
        $other = BlogCategory::factory()->create(['name' => 'Novidades']);

        $this->actingAs($this->adminUser())
            ->from(route('admin.blog.categories.index'))
            ->put(route('admin.blog.categories.update', $edited), ['name' => 'Novidades', 'keep_slug' => '1'])
            ->assertSessionHasErrors(['name'], null, BlogCategoryController::updateBag($edited));

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.categories.index')));

        $editedDialog = $this->adminElement($xpath, '//dialog[@id="categoria-'.$edited->id.'-dialogo"]');
        $this->assertSame('true', $editedDialog->getAttribute('data-dialog-open-on-load'));
        $editedInput = $this->adminElement($xpath, './/input[@id="categoria-'.$edited->id.'-nome"]', $editedDialog);
        $this->assertSame('true', $editedInput->getAttribute('aria-invalid'));
        $this->assertSame('Novidades', $editedInput->getAttribute('value'));
        $this->assertTrue($this->adminElement($xpath, './/input[@id="categoria-'.$edited->id.'-manter-endereco"]', $editedDialog)->hasAttribute('checked'));

        $otherInput = $this->adminElement($xpath, '//input[@id="categoria-'.$other->id.'-nome"]');
        $this->assertFalse($otherInput->hasAttribute('aria-invalid'));
        $this->assertSame('Novidades', $otherInput->getAttribute('value'));

        $newInput = $this->adminElement($xpath, '//input[@id="nova-categoria-nome"]');
        $this->assertFalse($newInput->hasAttribute('aria-invalid'));
        $this->assertSame('', $newInput->getAttribute('value'));
    }

    public function test_renaming_a_category_keeps_the_public_address_unless_asked(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Dicas', 'slug' => 'dicas']);

        $this->actingAs($this->adminUser())
            ->put(route('admin.blog.categories.update', $category), ['name' => 'Dicas de manutenção', 'is_active' => '1', 'keep_slug' => '1'])
            ->assertSessionHas('success', 'Categoria atualizada.');
        $this->assertSame('dicas', $category->fresh()->slug);
        $this->assertSame('Dicas de manutenção', $category->fresh()->name);

        $this->actingAs($this->adminUser())
            ->put(route('admin.blog.categories.update', $category), ['name' => 'Dicas de manutenção', 'is_active' => '1'])
            ->assertSessionHas('success', 'Categoria atualizada. O endereço público mudou para /blog/categoria/dicas-de-manutencao.');
        $this->assertSame('dicas-de-manutencao', $category->fresh()->slug);
    }

    public function test_category_dialog_fields_have_visible_labels_and_the_address_hint(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Dicas', 'slug' => 'dicas']);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.categories.index')));
        $dialog = $this->adminElement($xpath, '//dialog[@id="categoria-'.$category->id.'-dialogo"]');

        $this->assertSame('Editar categoria Dicas', $this->adminText($this->adminElement($xpath, './/h2', $dialog)));
        foreach (['nome' => 'Nome', 'descricao' => 'Descrição'] as $field => $label) {
            $this->assertStringStartsWith($label, $this->adminText($this->adminElement($xpath, './/label[@for="categoria-'.$category->id.'-'.$field.'"]', $dialog)));
        }
        $this->assertStringContainsString('/blog/categoria/dicas', $this->adminText($this->adminElement($xpath, './/label[@for="categoria-'.$category->id.'-manter-endereco"]', $dialog)));

        $row = $this->adminElement($xpath, '//tbody/tr[th[@scope="row"][contains(., "Dicas")]]');
        $this->assertSame(0, $xpath->query('.//input[not(@type="hidden")]', $row)->length);
        $this->assertSame('Ativa', $this->adminText($this->adminElement($xpath, './/*[@data-slot="badge"]', $row)));
    }
}

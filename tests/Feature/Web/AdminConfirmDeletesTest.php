<?php

namespace Tests\Feature\Web;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Exclusões do admin passam pelo <x-ui.confirm-dialog> (data-confirm, botão vermelho e texto com a
 * consequência), nunca pelo confirm() nativo do navegador.
 */
class AdminConfirmDeletesTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_admin_views_do_not_use_the_native_confirm(): void
    {
        foreach (File::allFiles(resource_path('views/admin')) as $file) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\w.$-])confirm\(/', $file->getContents(), $file->getRelativePathname().' ainda usa confirm() nativo.');
            $this->assertStringNotContainsString('onsubmit=', $file->getContents(), $file->getRelativePathname());
        }
    }

    public function test_brand_deletion_asks_with_the_app_dialog_and_names_the_consequence(): void
    {
        $brand = VehicleBrand::factory()->create(['name' => 'Peugeot']);
        VehicleModel::factory()->count(2)->create(['vehicle_brand_id' => $brand->id]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.edit', $brand)));
        $form = $this->deleteForm($xpath, route('admin.brands.destroy', $brand));

        $this->assertSame('Excluir a marca Peugeot?', $form->getAttribute('data-confirm-title'));
        $this->assertSame('A marca Peugeot e os 2 modelos dela serão excluídos. Veículos já cadastrados não mudam. Não é possível desfazer.', $form->getAttribute('data-confirm'));
        $this->assertSame('Excluir marca', $form->getAttribute('data-confirm-action-label'));
        $this->assertSame(1, $xpath->query('//dialog[@data-ui-confirm-dialog][@role="alertdialog"]')->length);
    }

    public function test_model_deletion_asks_with_the_app_dialog(): void
    {
        $brand = VehicleBrand::factory()->create(['name' => 'Renault']);
        $model = VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id, 'name' => 'Kwid']);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.show', $brand)));
        $form = $this->deleteForm($xpath, route('admin.models.destroy', $model));

        $this->assertSame('Excluir o modelo Kwid?', $form->getAttribute('data-confirm-title'));
        $this->assertStringContainsString('Não é possível desfazer.', $form->getAttribute('data-confirm'));
        $this->assertSame('Excluir modelo', $form->getAttribute('data-confirm-action-label'));
        $this->assertSame('Excluir modelo', $this->adminText($form));
        $this->assertSame('menuitem', $form->getAttribute('role'), 'A exclusão fica no menu ⋯ da linha.');
        $this->assertSame('Ações para o modelo Kwid', $this->adminElement($xpath, '//*[@data-slot="row-actions"]//button[@aria-haspopup="menu"]')->getAttribute('aria-label'));
    }

    public function test_brand_list_offers_the_deletion_in_the_row_menu(): void
    {
        $brand = VehicleBrand::factory()->create(['name' => 'Citroën']);
        VehicleModel::factory()->count(3)->create(['vehicle_brand_id' => $brand->id]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.index')));
        $trigger = $this->deleteForm($xpath, route('admin.brands.destroy', $brand));

        $this->assertSame('Excluir a marca Citroën?', $trigger->getAttribute('data-confirm-title'));
        $this->assertSame('A marca Citroën e os 3 modelos dela serão excluídos. Veículos já cadastrados não mudam. Não é possível desfazer.', $trigger->getAttribute('data-confirm'));
    }

    public function test_post_deletion_asks_with_the_app_dialog(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Revisão dos 10 mil km']);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.edit', $post)));
        $form = $this->deleteForm($xpath, route('admin.blog.destroy', $post));

        $this->assertSame('Excluir o artigo?', $form->getAttribute('data-confirm-title'));
        $this->assertStringContainsString('Revisão dos 10 mil km', $form->getAttribute('data-confirm'));
        $this->assertSame('Excluir artigo', $form->getAttribute('data-confirm-action-label'));
    }

    public function test_category_deletion_says_the_posts_stay_without_category(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Dicas']);
        BlogPost::factory()->count(3)->create(['blog_category_id' => $category->id]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.categories.index')));
        $form = $this->deleteForm($xpath, route('admin.blog.categories.destroy', $category));

        $this->assertSame('Excluir a categoria Dicas?', $form->getAttribute('data-confirm-title'));
        $this->assertSame('A categoria Dicas sai do blog e os 3 artigos dela ficam sem categoria. Não é possível desfazer.', $form->getAttribute('data-confirm'));
        $this->assertSame('Excluir categoria', $form->getAttribute('data-confirm-action-label'));
    }

    public function test_confirmed_deletion_still_reaches_the_server(): void
    {
        $brand = VehicleBrand::factory()->create();

        $this->actingAs($this->adminUser())
            ->delete(route('admin.brands.destroy', $brand))
            ->assertRedirect(route('admin.brands.index'));

        $this->assertModelMissing($brand);
    }

    /**
     * O formulário de exclusão ou, no menu ⋯ da linha (x-ui.dropdown-item com action), o botão de
     * envio dele: resources/js/ui/confirm.js aceita data-confirm nos dois.
     */
    private function deleteForm(DOMXPath $xpath, string $action): DOMElement
    {
        $form = $this->adminElement($xpath, '//form[@action="'.$action.'"][.//input[@name="_method"][@value="DELETE"]]');
        $trigger = $form->hasAttribute('data-confirm') ? $form : $this->adminElement($xpath, './/button[@type="submit"][@data-confirm]', $form);

        $this->assertNotSame('', $trigger->getAttribute('data-confirm'));
        $this->assertSame('danger', $trigger->getAttribute('data-confirm-variant'));

        return $trigger;
    }
}

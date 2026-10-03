<?php

namespace Tests\Feature\Web;

use App\Models\BlogCategory;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\VehicleBrand;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Avisos de sessão com os termos do glossário: "Excluir" apaga de vez e o aviso diz "excluído(a)";
 * "Modelo de garantia" e "OS" no portal da oficina (nunca "template" nem "serviço removido").
 */
class GlossaryFlashMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_messages(): void
    {
        $admin = User::factory()->asUser()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.brands.store'), ['name' => 'Fiat'])->assertSessionHas('success', 'Marca cadastrada.');

        $brand = VehicleBrand::firstWhere('name', 'Fiat');

        $this->actingAs($admin)->put(route('admin.brands.update', $brand), ['name' => 'Fiat', 'is_active' => '1'])->assertSessionHas('success', 'Marca atualizada.');
        $this->actingAs($admin)->delete(route('admin.brands.destroy', $brand))->assertSessionHas('success', 'Marca excluída.');
    }

    public function test_model_messages(): void
    {
        $admin = User::factory()->asUser()->create(['is_admin' => true]);
        $brand = VehicleBrand::factory()->create();

        $this->actingAs($admin)->post(route('admin.brands.models.store', $brand), ['name' => 'Argo'])->assertSessionHas('success', 'Modelo cadastrado.');

        $model = $brand->models()->firstWhere('name', 'Argo');

        $this->actingAs($admin)->put(route('admin.models.update', $model), ['name' => 'Argo', 'is_active' => '1'])->assertSessionHas('success', 'Modelo atualizado.');
        $this->actingAs($admin)->delete(route('admin.models.destroy', $model))->assertSessionHas('success', 'Modelo excluído.');
    }

    public function test_blog_category_messages(): void
    {
        $admin = User::factory()->asUser()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.blog.categories.store'), ['name' => 'Documentação'])->assertSessionHas('success', 'Categoria criada.');

        $category = BlogCategory::firstWhere('name', 'Documentação');

        // O formulário mantém o endereço público por padrão (keep_slug); trocar o endereço acrescenta o novo ao aviso.
        $this->actingAs($admin)->put(route('admin.blog.categories.update', $category), ['name' => 'Documentos', 'is_active' => '1', 'keep_slug' => '1'])->assertSessionHas('success', 'Categoria atualizada.');
        $category->refresh();

        $this->actingAs($admin)->delete(route('admin.blog.categories.destroy', $category))->assertSessionHas('success', 'Categoria excluída.');
    }

    public function test_warranty_template_messages_say_modelo_de_garantia(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($user->workshop)->create();

        $this->actingAs($user)
            ->delete(route('workshop.warranty-templates.destroy', $template))
            ->assertRedirect(route('workshop.warranty-templates.index'))
            ->assertSessionHas('success', 'Modelo de garantia excluído.');
    }

    public function test_workshop_os_deletion_message(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $user->workshop->id]);

        $this->actingAs($user)
            ->delete(route('workshop.maintenances.destroy', $maintenance))
            ->assertRedirect(route('workshop.maintenances.index'))
            ->assertSessionHas('success', 'OS excluída.');
    }
}

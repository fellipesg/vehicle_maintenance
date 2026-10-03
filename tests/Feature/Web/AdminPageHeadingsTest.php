<?php

namespace Tests\Feature\Web;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Toda tela do admin tem um único H1 (do <x-ui.page-header>), igual ao nome da página no <title>,
 * e a trilha da topbar termina nela. Os links "← Voltar…" avulsos saíram.
 */
class AdminPageHeadingsTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_every_admin_page_has_one_h1_matching_the_title_and_a_trail(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->asUser()->create(['name' => 'Bruna Proprietária']);
        $vehicle = Vehicle::factory()->create(['brand' => 'Fiat', 'model' => 'Argo']);
        $owner->vehicles()->attach($vehicle->id, ['purchase_date' => now(), 'is_current_owner' => true, 'tenant_id' => $owner->tenant_id]);
        Maintenance::factory()->for($vehicle)->for($owner)->create();
        $brand = VehicleBrand::factory()->create(['name' => 'Chevrolet']);
        VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id, 'name' => 'Onix']);
        $post = BlogPost::factory()->create(['title' => 'Quando trocar o óleo']);
        BlogCategory::factory()->create();

        $pages = [
            [route('admin.dashboard'), 'Visão geral', ['Visão geral']],
            [route('admin.users.index'), 'Usuários', ['Cadastros', 'Usuários']],
            [route('admin.users.show', $owner), 'Bruna Proprietária', ['Cadastros', 'Usuários', 'Bruna Proprietária']],
            [route('admin.workshops.index'), 'Oficinas', ['Cadastros', 'Oficinas']],
            [route('admin.maps.workshops'), 'Mapa de oficinas', ['Cadastros', 'Oficinas', 'Mapa de oficinas']],
            [route('admin.maps.users'), 'Mapa de proprietários', ['Cadastros', 'Usuários', 'Mapa de proprietários']],
            [route('admin.vehicles.index'), 'Veículos', ['Frota', 'Veículos']],
            [route('admin.vehicles.show', $vehicle), 'Fiat Argo', ['Frota', 'Veículos', 'Fiat Argo']],
            [route('admin.maintenances.index'), 'Manutenções', ['Frota', 'Manutenções']],
            [route('admin.blog.index'), 'Artigos do blog', ['Conteúdo', 'Artigos do blog']],
            [route('admin.blog.create'), 'Novo artigo', ['Conteúdo', 'Artigos do blog', 'Novo artigo']],
            [route('admin.blog.edit', $post), 'Editar artigo', ['Conteúdo', 'Artigos do blog', 'Editar artigo']],
            [route('admin.blog.categories.index'), 'Categorias do blog', ['Conteúdo', 'Categorias do blog']],
            [route('admin.brands.index'), 'Marcas e modelos', ['Catálogo', 'Marcas e modelos']],
            [route('admin.brands.create'), 'Nova marca', ['Catálogo', 'Marcas e modelos', 'Nova marca']],
            [route('admin.brands.show', $brand), 'Chevrolet', ['Catálogo', 'Marcas e modelos', 'Chevrolet']],
            [route('admin.brands.edit', $brand), 'Editar marca', ['Catálogo', 'Marcas e modelos', 'Chevrolet', 'Editar marca']],
        ];

        foreach ($pages as [$url, $heading, $trail]) {
            $response = $this->actingAs($admin)->get($url);
            $xpath = $this->adminPage($response);

            $headings = $this->adminElements($xpath, '//h1');
            $this->assertCount(1, $headings, "{$url} deveria ter um único <h1>.");
            $this->assertSame($heading, $this->adminText($headings[0]), "H1 de {$url}.");
            $this->assertSame('page-header-title', $headings[0]->getAttribute('data-slot'), "{$url}: o H1 vem do <x-ui.page-header>.");

            $title = $this->adminText($this->adminElement($xpath, '//title'));
            $this->assertStringStartsWith($heading.' · Admin · ', $title, "O <title> de {$url} começa pelo H1.");

            $crumbs = $this->adminTrail($xpath);
            $this->assertSame($trail, array_column($crumbs, 'label'), "Trilha de {$url}.");
            $this->assertTrue(end($crumbs)['current'], "{$url}: o último item da trilha é a página atual.");
            $this->assertNull(end($crumbs)['href'], "{$url}: a página atual não é link na trilha.");

            $this->assertStringNotContainsString('← ', $response->getContent(), "{$url} ainda tem um link de voltar avulso.");
        }
    }

    public function test_trail_links_climb_the_hierarchy(): void
    {
        $brand = VehicleBrand::factory()->create(['name' => 'Fiat']);
        $owner = User::factory()->asUser()->create(['name' => 'Carla']);

        $brandTrail = $this->adminTrail($this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.edit', $brand))));
        $this->assertSame([null, route('admin.brands.index'), route('admin.brands.show', $brand), null], array_column($brandTrail, 'href'));

        $userTrail = $this->adminTrail($this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.show', $owner))));
        $this->assertSame(route('admin.users.index'), $userTrail[1]['href']);
    }

    public function test_admin_views_do_not_bring_their_own_container_or_heading_markup(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views/admin')) as $file) {
            $source = $file->getContents();

            if (preg_match('/<h1[\s>]/', $source)) {
                $offenders[] = $file->getRelativePathname().': <h1> escrito à mão (use <x-ui.page-header>)';
            }

            if (preg_match('/class="[^"]*\bmx-auto max-w-\w+ px-4 py-\d/', $source)) {
                $offenders[] = $file->getRelativePathname().': contêiner próprio (o layout já tem)';
            }

            if (str_contains($source, "@section('page_heading'")) {
                $offenders[] = $file->getRelativePathname().': page_heading (a topbar mostra a trilha, não um título)';
            }
        }

        $this->assertSame([], $offenders);
    }
}

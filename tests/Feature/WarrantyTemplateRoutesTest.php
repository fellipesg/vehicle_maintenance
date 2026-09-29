<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WarrantyTemplateRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('workshop.warranty-templates.show'));
    }

    public function test_existing_route_names_are_kept(): void
    {
        foreach (['index', 'create', 'store', 'edit', 'update', 'destroy'] as $action) {
            $this->assertTrue(
                Route::has("workshop.warranty-templates.{$action}"),
                "Route workshop.warranty-templates.{$action} is missing.",
            );
        }
    }

    public function test_get_on_a_template_url_is_not_a_server_error(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($user->workshop)->create();

        $this->actingAs($user)
            ->get('/oficina/garantias/templates/'.$template->id)
            ->assertMethodNotAllowed();
    }

    public function test_create_and_edit_pages_still_open(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($user->workshop)->create();

        $this->actingAs($user)
            ->get(route('workshop.warranty-templates.create'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('workshop.warranty-templates.edit', $template))
            ->assertOk();
    }
}

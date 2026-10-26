<?php

namespace Tests\Feature\Web;

use App\Features\MaintenancePhotos;
use App\Models\User;
use App\Support\Maintenance\MaintenancePhotoAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * O admin liga e desliga as fotos das manutenções (App\Features\MaintenancePhotos) em um usuário,
 * uma oficina ou uma loja. Só administrador altera.
 */
class AdminMaintenancePhotosFeatureTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_admin_turns_the_feature_on_and_off_for_a_user(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->asUser()->create();
        Log::spy();

        $this->actingAs($admin)
            ->from(route('admin.users.show', $owner))
            ->put(route('admin.users.maintenance-photos', $owner), ['enabled' => '1'])
            ->assertRedirect(route('admin.users.show', $owner))
            ->assertSessionHas('success');

        $this->assertTrue(Feature::for($owner)->active(MaintenancePhotos::class));
        $this->assertTrue(MaintenancePhotoAccess::allows($owner->fresh()));
        Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message, array $context): bool => $context['admin_id'] === $admin->id
            && $context['scope_type'] === $owner->getMorphClass()
            && $context['scope_id'] === $owner->id
            && $context['enabled'] === true);

        $this->actingAs($admin)->put(route('admin.users.maintenance-photos', $owner), ['enabled' => '0'])->assertRedirect();

        $this->assertFalse(Feature::for($owner)->active(MaintenancePhotos::class));
    }

    public function test_admin_turns_the_feature_on_for_a_workshop(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create()->refresh();

        $this->actingAs($this->adminUser())
            ->put(route('admin.workshops.maintenance-photos', $workshopUser->workshop), ['enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue(Feature::for($workshopUser->workshop)->active(MaintenancePhotos::class));
        $this->assertFalse(Feature::for($workshopUser)->active(MaintenancePhotos::class));
        $this->assertTrue(MaintenancePhotoAccess::allows($workshopUser->fresh()));
    }

    public function test_admin_turns_the_feature_on_for_a_garage(): void
    {
        $garageUser = User::factory()->asGarage()->create()->refresh();

        $this->actingAs($this->adminUser())
            ->put(route('admin.garages.maintenance-photos', $garageUser->garage), ['enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue(Feature::for($garageUser->garage)->active(MaintenancePhotos::class));
        $this->assertTrue(MaintenancePhotoAccess::allows($garageUser->fresh()));
    }

    public function test_non_admins_cannot_change_the_feature(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshopUser = User::factory()->asWorkshop()->create()->refresh();
        $garageUser = User::factory()->asGarage()->create()->refresh();
        $routes = [
            route('admin.users.maintenance-photos', $owner),
            route('admin.workshops.maintenance-photos', $workshopUser->workshop),
            route('admin.garages.maintenance-photos', $garageUser->garage),
        ];

        foreach ($routes as $url) {
            $this->actingAs($owner)->put($url, ['enabled' => '1'])->assertRedirect();
            $this->actingAs($workshopUser)->put($url, ['enabled' => '1'])->assertRedirect();
        }

        auth()->logout();
        $this->put($routes[0], ['enabled' => '1'])->assertRedirect();

        $this->assertFalse(MaintenancePhotoAccess::allows($owner->fresh()));
        $this->assertFalse(MaintenancePhotoAccess::allows($workshopUser->fresh()));
        $this->assertFalse(MaintenancePhotoAccess::allows($garageUser->fresh()));
    }

    public function test_the_value_is_required_and_boolean(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($this->adminUser())
            ->put(route('admin.users.maintenance-photos', $owner), [])
            ->assertSessionHasErrors('enabled');
        $this->actingAs($this->adminUser())
            ->put(route('admin.users.maintenance-photos', $owner), ['enabled' => 'talvez'])
            ->assertSessionHasErrors('enabled');
    }

    public function test_user_detail_shows_the_toggles_for_the_account_and_its_workshop(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create(['name' => 'Oficina do Zé'])->refresh();
        Feature::for($workshopUser->workshop)->activate(MaintenancePhotos::class);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.show', $workshopUser)));
        $toggles = $this->adminElements($xpath, '//*[@data-slot="maintenance-photos-toggle"]');

        $this->assertCount(2, $toggles);
        $this->assertSame('false', $toggles[0]->getAttribute('data-enabled'));
        $this->assertStringContainsString('Ligadas pela oficina', $this->adminText($toggles[0]));
        $this->assertSame('true', $toggles[1]->getAttribute('data-enabled'));
        $this->assertSame(
            route('admin.workshops.maintenance-photos', $workshopUser->workshop),
            $this->adminElement($xpath, './/form', $toggles[1])->getAttribute('action'),
        );
    }

    public function test_user_detail_shows_the_garage_toggle_for_a_dealer(): void
    {
        $garageUser = User::factory()->asGarage()->create()->refresh();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.show', $garageUser)));
        $toggles = $this->adminElements($xpath, '//*[@data-slot="maintenance-photos-toggle"]');

        $this->assertCount(2, $toggles);
        $this->assertSame(
            route('admin.garages.maintenance-photos', $garageUser->garage),
            $this->adminElement($xpath, './/form', $toggles[1])->getAttribute('action'),
        );
    }

    public function test_user_list_marks_accounts_with_the_feature(): void
    {
        $with = User::factory()->asUser()->create(['name' => 'Com Fotos']);
        User::factory()->asUser()->create(['name' => 'Sem Fotos']);
        Feature::for($with)->activate(MaintenancePhotos::class);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.index')));
        $rows = collect($this->adminElements($xpath, '//tbody/tr'));

        $badges = fn (string $name): int => count($this->adminElements($xpath, './/*[@data-slot="maintenance-photos-badge"]', $rows->first(fn ($row): bool => str_contains($this->adminText($row), $name))));

        $this->assertSame(1, $badges('Com Fotos'));
        $this->assertSame(0, $badges('Sem Fotos'));
    }

    public function test_workshop_list_shows_the_state_of_each_workshop(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create()->refresh();
        Feature::for($workshopUser->workshop)->activate(MaintenancePhotos::class);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.workshops.index')));

        $this->assertStringContainsString('Ligadas', $this->adminText($this->adminElement($xpath, '//*[@data-slot="maintenance-photos-status"]')));
    }

    public function test_garage_list_has_a_toggle_per_store(): void
    {
        User::factory()->asGarage()->create();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.garages.index', ['filtro' => 'todas'])));

        $this->adminElement($xpath, '//*[@data-slot="maintenance-photos-toggle"]');
    }
}

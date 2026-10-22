<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preferência do proprietário de levar as fotos das OS no PDF do histórico: users.export_include_photos
 * (nulo = nunca perguntado), lida em GET /api/v1/me e gravada em PUT /api/v1/me e em /conta.
 */
class ExportPhotosPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preference_is_null_until_the_owner_answers(): void
    {
        $user = User::factory()->asUser()->create()->refresh();

        $this->assertNull($user->export_include_photos);

        $this->actingAsApiUser($user);
        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.export_include_photos', null);
    }

    public function test_put_me_accepts_only_the_preference_field(): void
    {
        $user = $this->actingAsApiUser();

        $this->putJson('/api/v1/me', ['export_include_photos' => true])
            ->assertOk()
            ->assertJsonPath('data.export_include_photos', true)
            ->assertJsonPath('data.name', $user->name);

        $this->assertTrue($user->fresh()->export_include_photos);
        $this->getJson('/api/v1/me')->assertJsonPath('data.export_include_photos', true);

        $this->putJson('/api/v1/me', ['export_include_photos' => false])
            ->assertOk()
            ->assertJsonPath('data.export_include_photos', false);

        $this->assertFalse($user->fresh()->export_include_photos);
    }

    public function test_profile_update_without_the_preference_keeps_it(): void
    {
        $user = $this->actingAsApiUser(User::factory()->asUser()->create(['export_include_photos' => true]));

        $this->putJson('/api/v1/me', ['phone' => '43999998888'])->assertOk();

        $this->assertTrue($user->fresh()->export_include_photos);
    }

    public function test_put_me_rejects_a_non_boolean_preference(): void
    {
        $user = $this->actingAsApiUser();

        $this->putJson('/api/v1/me', ['export_include_photos' => 'talvez'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('export_include_photos');

        $this->putJson('/api/v1/me', ['export_include_photos' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('export_include_photos');

        $this->assertNull($user->fresh()->export_include_photos);
    }

    public function test_web_account_page_saves_the_preference_for_owners(): void
    {
        $user = User::factory()->asUser()->create();

        $this->withoutVite()->actingAs($user)->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Incluir fotos das manutenções');

        $this->actingAs($user)
            ->put(route('account.preferences'), ['export_include_photos' => '1'])
            ->assertRedirect(route('account.edit'));
        $this->assertTrue($user->fresh()->export_include_photos);

        $this->actingAs($user)
            ->put(route('account.preferences'), ['export_include_photos' => '0'])
            ->assertRedirect(route('account.edit'));
        $this->assertFalse($user->fresh()->export_include_photos);
    }

    public function test_web_account_page_hides_the_preference_from_company_accounts(): void
    {
        $garage = User::factory()->asGarage()->create();

        $this->withoutVite()->actingAs($garage)->get(route('account.edit'))
            ->assertOk()
            ->assertDontSee('Incluir fotos das manutenções');

        $this->actingAs($garage)
            ->put(route('account.preferences'), ['export_include_photos' => '1'])
            ->assertForbidden();
    }
}

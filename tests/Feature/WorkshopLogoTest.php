<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkshopLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeCoversDisk('r2');
    }

    public function test_workshop_can_upload_logo_on_profile_update(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $workshop = $user->workshop;
        $file = UploadedFile::fake()->image('logo.jpg', 200, 200);

        $this->actingAs($user)
            ->put(route('workshop.profile.update'), [
                'name' => $workshop->name,
                'phone' => $workshop->phone,
                'cep' => $workshop->cep,
                'street' => $workshop->street,
                'number' => $workshop->number,
                'neighborhood' => $workshop->neighborhood,
                'city' => $workshop->city,
                'state' => $workshop->state,
                'logo' => $file,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.profile.show'));

        $workshop->refresh();

        $this->assertNotNull($workshop->logo_path);
        $this->assertStringStartsWith(AppStorage::WORKSHOP_LOGOS_PREFIX, $workshop->logo_path);
        Storage::disk('r2')->assertExists($workshop->logo_path);
        $this->assertNotNull($workshop->logoUrl());
    }

    public function test_workshop_can_upload_logo_on_profile_create(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $user->workshop->delete();
        $user->unsetRelation('workshop');

        $file = UploadedFile::fake()->image('logo.png');

        $this->actingAs($user)
            ->post(route('workshop.profile.store'), [
                'name' => 'Oficina Teste',
                'phone' => '11999999999',
                'cep' => '01310100',
                'street' => 'Av Paulista',
                'number' => '1000',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SP',
                'logo' => $file,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.dashboard'));

        $workshop = $user->fresh()->workshop;

        $this->assertNotNull($workshop);
        $this->assertStringStartsWith(AppStorage::WORKSHOP_LOGOS_PREFIX, $workshop->logo_path);
        Storage::disk('r2')->assertExists($workshop->logo_path);
    }

    public function test_profile_show_displays_logo_when_present(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $workshop = $user->workshop;
        $workshop->update([
            'logo_path' => AppStorage::WORKSHOP_LOGOS_PREFIX.'1_test.jpg',
        ]);

        Storage::disk('r2')->put($workshop->logo_path, 'fake-image');

        $this->assertDatabaseHas('workshops', [
            'id' => $workshop->id,
            'logo_path' => AppStorage::WORKSHOP_LOGOS_PREFIX.'1_test.jpg',
        ]);
        $this->assertNotNull($workshop->fresh()->logoUrl());

        $this->actingAs($user)
            ->get(route('workshop.profile.show'))
            ->assertOk()
            ->assertSee('<p class="mb-2 font-semibold">Logo</p>', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workshop;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkshopControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeCoversDisk('r2');
    }

    public function test_can_list_workshops_publicly(): void
    {
        Workshop::factory()->create(['name' => 'Oficina Pública']);

        $this->getJson('/api/v1/workshops')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Oficina Pública']);
    }

    public function test_workshop_user_can_create_workshop_with_tenant(): void
    {
        $user = $this->actingAsApiUser(User::factory()->asWorkshop()->create());

        $this->postJson('/api/v1/workshops', [
            'name' => 'Mecânica do Bairro',
            'phone' => '11999999999',
            'cep' => '01310100',
            'street' => 'Av Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Mecânica do Bairro');

        $this->assertDatabaseHas('workshops', [
            'name' => 'Mecânica do Bairro',
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function test_regular_user_cannot_create_workshop(): void
    {
        $this->actingAsApiUser();

        $this->postJson('/api/v1/workshops', [
            'name' => 'Oficina Ilegal',
            'phone' => '11999999999',
            'cep' => '01310100',
            'street' => 'Rua Teste',
            'number' => '1',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ])->assertForbidden();
    }

    public function test_workshop_user_can_update_own_workshop(): void
    {
        $user = $this->actingAsApiUser(User::factory()->asWorkshop()->create());
        $workshop = Workshop::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => 'Nome Antigo',
        ]);

        $this->putJson("/api/v1/workshops/{$workshop->id}", [
            'name' => 'Nome Novo',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nome Novo');
    }

    public function test_workshop_user_cannot_update_other_workshop(): void
    {
        $user = $this->actingAsApiUser(User::factory()->asWorkshop()->create());
        $otherUser = User::factory()->asWorkshop()->create();
        $workshop = Workshop::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $otherUser->tenant_id,
        ]);

        $this->putJson("/api/v1/workshops/{$workshop->id}", [
            'name' => 'Tentativa',
        ])->assertForbidden();
    }

    public function test_workshop_user_can_upload_logo_on_api_update(): void
    {
        $user = $this->actingAsApiUser(User::factory()->asWorkshop()->create());
        $workshop = Workshop::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
        $file = UploadedFile::fake()->image('logo.jpg', 200, 200);

        $this->putJson("/api/v1/workshops/{$workshop->id}", [
            'name' => $workshop->name,
            'logo' => $file,
        ])->assertOk()
            ->assertJsonPath('data.logo_url', fn ($url) => $url !== null);

        $workshop->refresh();

        $this->assertNotNull($workshop->logo_path);
        $this->assertStringStartsWith(AppStorage::WORKSHOP_LOGOS_PREFIX, $workshop->logo_path);
        Storage::disk('r2')->assertExists($workshop->logo_path);
    }
}

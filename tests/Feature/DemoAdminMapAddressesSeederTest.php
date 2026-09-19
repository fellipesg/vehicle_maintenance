<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\DemoAdminMapAddressesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAdminMapAddressesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_does_not_overwrite_existing_city(): void
    {
        $user = User::factory()->asUser()->create([
            'email' => 'existing@vehicle-maintenance.test',
            'city' => 'Cidade Real',
            'street' => 'Rua Original',
        ]);

        $this->seed(DemoAdminMapAddressesSeeder::class);

        $user->refresh();

        $this->assertSame('Cidade Real', $user->city);
        $this->assertSame('Rua Original', $user->street);
    }

    public function test_seeder_fills_empty_workshop_address_only(): void
    {
        $workshop = Workshop::factory()->create([
            'street' => '',
            'city' => '',
            'neighborhood' => 'Centro',
            'cep' => '86050000',
            'state' => 'PR',
        ]);

        $this->seed(DemoAdminMapAddressesSeeder::class);

        $workshop->refresh();

        $this->assertNotSame('', $workshop->street);
        $this->assertNotSame('', $workshop->city);
    }
}

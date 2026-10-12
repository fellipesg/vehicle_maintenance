<?php

namespace Tests\Feature;

use App\Models\VehicleBrand;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sem o catálogo, marca e modelo ficam com lista fechada vazia e o formulário
     * de veículo do portal não pode ser enviado. Nada chamava o seeder fora dos
     * testes, então todo ambiente local novo nascia assim.
     */
    public function test_seeding_populates_the_vehicle_catalog(): void
    {
        $this->assertSame(0, VehicleBrand::count());

        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, VehicleBrand::where('is_active', true)->count());
        $this->assertGreaterThan(
            0,
            VehicleBrand::where('is_active', true)->first()->models()->count(),
        );
    }

    public function test_seeding_twice_does_not_duplicate_the_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);
        $brandsAfterFirstRun = VehicleBrand::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($brandsAfterFirstRun, VehicleBrand::count());
    }
}

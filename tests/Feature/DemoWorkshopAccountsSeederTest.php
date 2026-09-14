<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Models\Workshop;
use Database\Seeders\DemoWorkshopAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoWorkshopAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_assigns_maintenances(): void
    {
        $divesa = Workshop::factory()->create(['name' => 'Mercedes-Benz DIVESA Londrina']);
        $brothers = Workshop::factory()->create(['name' => 'Brothers Auto Service']);
        $dev = Workshop::factory()->create(['name' => 'Dev Oficina']);
        $vehicle = Vehicle::factory()->create();

        $brothersOrphan = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => null,
            'workshop_name' => 'Brothers Londrina',
        ]);

        $divesaMaintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $divesa->id,
            'workshop_name' => 'DIVESA',
        ]);

        $orphan = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => null,
            'workshop_name' => 'Vila Verde',
        ]);

        $seeder = new DemoWorkshopAccountsSeeder;
        $seeder->run();
        $seeder->run();

        $brothersOrphan->refresh();
        $divesaMaintenance->refresh();
        $orphan->refresh();

        $this->assertSame($brothers->id, $brothersOrphan->workshop_id);
        $this->assertSame(DemoWorkshopAccountsSeeder::BROTHERS_NAME, $brothersOrphan->workshop_name);
        $this->assertSame($divesa->id, $divesaMaintenance->workshop_id);
        $this->assertSame($dev->id, $orphan->workshop_id);

        $this->assertDatabaseHas('users', ['email' => DemoWorkshopAccountsSeeder::DIVESA_EMAIL]);
        $this->assertDatabaseHas('users', ['email' => DemoWorkshopAccountsSeeder::BROTHERS_EMAIL]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Services\TenantService;
use App\Services\Vehicle\VehicleMaintenanceReminderService;
use App\Services\Vehicle\VehicleMileageService;
use Illuminate\Database\Seeder;

/**
 * Dois veículos fictícios para testar lembretes por km (50k e 100k).
 */
class DemoNotificationVehicleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'fgoncalves2008@gmail.com')->first();

        if ($user === null) {
            $this->command?->warn('Usuário fgoncalves2008@gmail.com não encontrado.');

            return;
        }

        if (! $user->tenant_id) {
            (new TenantService)->createForUser($user);
            $user->refresh();
        }

        $workshop = Workshop::firstOrCreate(
            ['name' => 'Mecânica Vila Verde'],
            [
                'phone' => '4133229900',
                'whatsapp' => '41999887766',
                'cep' => '80240000',
                'street' => 'Rua das Palmeiras',
                'number' => '88',
                'neighborhood' => 'Vila Verde',
                'city' => 'Curitiba',
                'state' => 'PR',
            ]
        );

        $today = now()->startOfDay();

        $this->seedNearFiftyThousand($user, $workshop, $today);
        $this->seedNearOneHundredThousand($user, $workshop, $today);

        $this->command?->info('Veículos demo de notificação criados: NTF5A00 (~50k) e NTF9A00 (~100k).');
        $this->command?->info('Rode: php artisan maintenance:check-km-reminders');
    }

    private function seedNearFiftyThousand(User $user, Workshop $workshop, \Carbon\Carbon $today): void
    {
        $lastMaintenanceDate = $today->copy()->subMonths(9);
        $lastMaintenanceKm = 40_000;
        $targetNextDue = 50_000;
        $currentKm = 48_600;

        $vehicle = Vehicle::updateOrCreate(
            ['license_plate' => 'NTF5A00'],
            [
                'renavam' => '99887766554',
                'crv_number' => '998877665544332',
                'brand' => 'Toyota',
                'model' => 'Corolla XEi',
                'year' => 2019,
                'color' => 'Prata',
                'chassis' => '9BRBLWHE5K0123456',
                'engine' => '2ZRFE1234567',
                'motorization' => '2.0',
                'odometer_at_registration' => 22_000,
                'current_kilometers' => $currentKm,
            ]
        );

        $this->resetMaintenances($vehicle);
        $this->linkToUser($user, $vehicle, '2021-04-12');

        $this->createMaintenance($vehicle, $user, $workshop, [
            'maintenance_type' => 'Revisão 30.000 km',
            'maintenance_date' => $lastMaintenanceDate->copy()->subMonths(14)->toDateString(),
            'kilometers' => 30_000,
            'description' => 'Filtros, óleo e alinhamento.',
            'items' => [
                ['name' => 'Óleo sintético 5W30', 'quantity' => 4, 'unit_price' => 62.00, 'total_price' => 248.00],
                ['name' => 'Filtro de óleo', 'quantity' => 1, 'unit_price' => 45.00, 'total_price' => 45.00],
            ],
        ]);

        $this->createMaintenance($vehicle, $user, $workshop, [
            'maintenance_type' => 'Revisão 40.000 km',
            'maintenance_date' => $lastMaintenanceDate->toDateString(),
            'kilometers' => $lastMaintenanceKm,
            'description' => 'Revisão programada com balanceamento.',
            'items' => [
                ['name' => 'Kit revisão 40.000 km', 'quantity' => 1, 'unit_price' => 520.00, 'total_price' => 520.00],
                ['name' => 'Balanceamento', 'quantity' => 4, 'unit_price' => 35.00, 'total_price' => 140.00],
            ],
        ]);

        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());

        Vehicle::query()->whereKey($vehicle->id)->update(['current_kilometers' => $currentKm]);

        $this->logReminderPreview($vehicle->fresh(['maintenances']), $targetNextDue);
    }

    private function seedNearOneHundredThousand(User $user, Workshop $workshop, \Carbon\Carbon $today): void
    {
        $lastMaintenanceDate = $today->copy()->subMonths(7);
        $lastMaintenanceKm = 90_000;
        $targetNextDue = 100_000;
        $currentKm = 98_700;

        $vehicle = Vehicle::updateOrCreate(
            ['license_plate' => 'NTF9A00'],
            [
                'renavam' => '88776655443',
                'crv_number' => '887766554433221',
                'brand' => 'Honda',
                'model' => 'Civic EXL',
                'year' => 2017,
                'color' => 'Preto',
                'chassis' => '93HFB9640HZ123456',
                'engine' => 'R18Z91234567',
                'motorization' => '2.0',
                'odometer_at_registration' => 35_000,
                'current_kilometers' => $currentKm,
            ]
        );

        $this->resetMaintenances($vehicle);
        $this->linkToUser($user, $vehicle, '2019-11-20');

        $this->createMaintenance($vehicle, $user, $workshop, [
            'maintenance_type' => 'Revisão 80.000 km',
            'maintenance_date' => $lastMaintenanceDate->copy()->subMonths(16)->toDateString(),
            'kilometers' => 80_000,
            'description' => 'Fluido de freio e pastilhas dianteiras.',
            'items' => [
                ['name' => 'Pastilhas dianteiras', 'quantity' => 1, 'unit_price' => 380.00, 'total_price' => 380.00],
                ['name' => 'Fluido de freio DOT4', 'quantity' => 1, 'unit_price' => 95.00, 'total_price' => 95.00],
            ],
        ]);

        $this->createMaintenance($vehicle, $user, $workshop, [
            'maintenance_type' => 'Revisão 90.000 km',
            'maintenance_date' => $lastMaintenanceDate->toDateString(),
            'kilometers' => $lastMaintenanceKm,
            'description' => 'Revisão completa com correia auxiliar.',
            'items' => [
                ['name' => 'Correia auxiliar', 'quantity' => 1, 'unit_price' => 290.00, 'total_price' => 290.00],
                ['name' => 'Mão de obra revisão 90.000 km', 'quantity' => 1, 'unit_price' => 650.00, 'total_price' => 650.00],
            ],
        ]);

        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());

        Vehicle::query()->whereKey($vehicle->id)->update(['current_kilometers' => $currentKm]);

        $this->logReminderPreview($vehicle->fresh(['maintenances']), $targetNextDue);
    }

    private function resetMaintenances(Vehicle $vehicle): void
    {
        Maintenance::where('vehicle_id', $vehicle->id)->each(function (Maintenance $maintenance): void {
            $maintenance->items()->delete();
            $maintenance->delete();
        });
    }

    private function linkToUser(User $user, Vehicle $vehicle, string $purchaseDate): void
    {
        $user->vehicles()->syncWithoutDetaching([
            $vehicle->id => [
                'purchase_date' => $purchaseDate,
                'is_current_owner' => true,
                'tenant_id' => $user->tenant_id,
                'ownership_verified_at' => now(),
                'crlv_exercise_year' => (int) now()->format('Y'),
                'owner_document' => '00000000000',
                'ownership_type' => 'owner',
                'terms_accepted_at' => now(),
                'terms_version' => (string) config('legal.terms_version', '1'),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createMaintenance(Vehicle $vehicle, User $user, Workshop $workshop, array $payload): void
    {
        $items = $payload['items'];
        unset($payload['items']);

        $maintenance = Maintenance::create(array_merge($payload, [
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
            'service_category' => 'mechanical',
            'is_manufacturer_required' => false,
        ]));

        foreach ($items as $item) {
            MaintenanceItem::create(array_merge($item, [
                'maintenance_id' => $maintenance->id,
                'description' => null,
                'part_number' => null,
            ]));
        }
    }

    private function logReminderPreview(Vehicle $vehicle, int $expectedNextDue): void
    {
        $reminders = app(VehicleMaintenanceReminderService::class);
        $summary = $reminders->summarize($vehicle);

        $this->command?->line(sprintf(
            '%s %s (%s): %s km → próxima %s km (faltam %s km) | shouldNotify=%s',
            $vehicle->brand,
            $vehicle->model,
            $vehicle->license_plate,
            number_format((int) $vehicle->current_kilometers, 0, ',', '.'),
            number_format((int) ($summary['next_due_kilometers'] ?? 0), 0, ',', '.'),
            number_format((int) ($summary['kilometers_remaining'] ?? 0), 0, ',', '.'),
            $reminders->shouldNotify($vehicle) ? 'sim' : 'não',
        ));
    }
}

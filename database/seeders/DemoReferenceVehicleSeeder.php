<?php

namespace Database\Seeders;

use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Services\TenantService;
use App\Services\Vehicle\VehicleMileageService;
use Illuminate\Database\Seeder;

/**
 * Veículo fictício para demos/postagens — estrutura parecida com timeline real,
 * sem expor placa, chassi ou dados pessoais reais.
 */
class DemoReferenceVehicleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'fgoncalves2008@gmail.com')->first();

        if ($user === null) {
            $this->command?->warn('Usuário fgoncalves2008@gmail.com não encontrado. Rode FelipeVehicleSeeder ou crie o usuário antes.');

            return;
        }

        if (! $user->tenant_id) {
            (new TenantService)->createForUser($user);
            $user->refresh();
        }

        $premiumWorkshop = Workshop::firstOrCreate(
            ['name' => 'Premium Motors Centro'],
            [
                'phone' => '4333001100',
                'whatsapp' => '4333001100',
                'cep' => '86010000',
                'street' => 'Rua das Oficinas',
                'number' => '1200',
                'neighborhood' => 'Centro',
                'city' => 'Londrina',
                'state' => 'PR',
            ]
        );

        $brothersWorkshop = Workshop::firstOrCreate(
            ['name' => 'Brothers Auto Service'],
            [
                'phone' => '4333002200',
                'whatsapp' => '4333002200',
                'cep' => '86020000',
                'street' => 'Av. Automotiva',
                'number' => '450',
                'neighborhood' => 'Industrial',
                'city' => 'Londrina',
                'state' => 'PR',
            ]
        );

        $vehicle = Vehicle::updateOrCreate(
            ['license_plate' => 'REF8D24'],
            [
                'renavam' => '98765432109',
                'crv_number' => '999888777666555',
                'brand' => 'Mercedes-Benz',
                'model' => 'C 200',
                'year' => 2019,
                'color' => 'Prata',
                'chassis' => 'WDD2050421F123456',
                'engine' => '27492012345678',
                'motorization' => '184CV',
                'odometer_at_registration' => 80_000,
                'current_kilometers' => 110_000,
            ]
        );

        $user->vehicles()->syncWithoutDetaching([
            $vehicle->id => [
                'purchase_date' => '2021-04-12',
                'is_current_owner' => true,
                'tenant_id' => $user->tenant_id,
                'ownership_verified_at' => now(),
                'crlv_exercise_year' => 2026,
                'owner_document' => '00000000000',
                'ownership_type' => 'owner',
                'terms_accepted_at' => now(),
                'terms_version' => (string) config('legal.terms_version', '1'),
            ],
        ]);

        $maintenances = [
            [
                'maintenance_type' => 'Revisão 80.000 km',
                'maintenance_date' => '2025-06-14',
                'kilometers' => 80_000,
                'workshop_id' => $premiumWorkshop->id,
                'workshop_name' => 'Premium Motors Centro',
                'service_category' => 'mechanical',
                'description' => 'Revisão programada de 80.000 km com itens de filtro, fluidos e pastilhas.',
                'items' => [
                    ['name' => 'Filtro de poeira', 'quantity' => 1, 'unit_price' => 240.97, 'total_price' => 240.97],
                    ['name' => 'Jogo de peças, elemento filtro', 'quantity' => 1, 'unit_price' => 153.83, 'total_price' => 153.83],
                    ['name' => 'Lubrificante Sint. 5W40 MBB 229.5', 'quantity' => 7, 'unit_price' => 85.90, 'total_price' => 601.30],
                    ['name' => 'Fluido freio DOT4 Plus', 'quantity' => 2, 'unit_price' => 45.00, 'total_price' => 90.00],
                    ['name' => 'Pastilha do freio a disco', 'quantity' => 1, 'unit_price' => 1619.68, 'total_price' => 1619.68],
                    ['name' => 'Elemento filtro de ar', 'quantity' => 1, 'unit_price' => 436.00, 'total_price' => 436.00],
                    ['name' => 'Filtro habitáculo', 'quantity' => 1, 'unit_price' => 365.00, 'total_price' => 365.00],
                    ['name' => 'Mão de obra revisão', 'quantity' => 1, 'unit_price' => 2107.72, 'total_price' => 2107.72],
                ],
            ],
            [
                'maintenance_type' => 'Revisão B (Assyst B)',
                'maintenance_date' => '2026-03-10',
                'kilometers' => 95_000,
                'workshop_id' => $premiumWorkshop->id,
                'workshop_name' => 'Premium Motors Centro',
                'service_category' => 'mechanical',
                'description' => 'Revisão B com troca de filtros, fluido de freio e bateria AGM.',
                'items' => [
                    ['name' => 'Filtro de poeira (complemento)', 'quantity' => 1, 'unit_price' => 258.32, 'total_price' => 258.32],
                    ['name' => 'Lubrificante 229.51 5W30', 'quantity' => 7, 'unit_price' => 85.00, 'total_price' => 595.00],
                    ['name' => 'Bateria 12V AGM 80AH', 'quantity' => 1, 'unit_price' => 3316.68, 'total_price' => 3316.68],
                ],
            ],
            [
                'maintenance_type' => 'Revisão preventiva',
                'maintenance_date' => '2026-03-12',
                'kilometers' => 105_103,
                'workshop_id' => $premiumWorkshop->id,
                'workshop_name' => 'Premium Motors Centro',
                'service_category' => 'mechanical',
                'description' => 'Inspeção complementar com substituição de pastilhas dianteiras.',
                'items' => [
                    ['name' => 'Pastilha dianteira', 'quantity' => 1, 'unit_price' => 980.00, 'total_price' => 980.00],
                    ['name' => 'Fluido limpador parabrisa', 'quantity' => 2, 'unit_price' => 28.50, 'total_price' => 57.00],
                    ['name' => 'Diagnóstico eletrônico', 'quantity' => 1, 'unit_price' => 2768.00, 'total_price' => 2768.00],
                ],
            ],
            [
                'maintenance_type' => 'Troca Válvula Termostática',
                'maintenance_date' => '2026-08-15',
                'kilometers' => 110_000,
                'workshop_id' => $brothersWorkshop->id,
                'workshop_name' => 'Brothers Auto Service',
                'service_category' => 'other',
                'description' => 'Substituição da válvula termostática por indicação de temperatura instável.',
                'items' => [
                    ['name' => 'Carcaça válvula termostática sedã premium', 'quantity' => 1, 'unit_price' => 1499.00, 'total_price' => 1499.00],
                ],
            ],
        ];

        foreach ($maintenances as $payload) {
            $items = $payload['items'];
            unset($payload['items']);

            $maintenance = Maintenance::updateOrCreate(
                [
                    'vehicle_id' => $vehicle->id,
                    'maintenance_type' => $payload['maintenance_type'],
                    'maintenance_date' => $payload['maintenance_date'],
                ],
                array_merge($payload, [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'is_manufacturer_required' => true,
                ]),
            );

            $maintenance->items()->delete();

            foreach ($items as $item) {
                MaintenanceItem::create(array_merge($item, [
                    'maintenance_id' => $maintenance->id,
                    'description' => null,
                    'part_number' => null,
                ]));
            }
        }

        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());

        $this->command?->info('Veículo demo REF8D24 (Mercedes-Benz C 200) vinculado a '.$user->email);
        $this->command?->info('4 manutenções fictícias — timeline ~110k km, próxima revisão ~120k km');
    }
}

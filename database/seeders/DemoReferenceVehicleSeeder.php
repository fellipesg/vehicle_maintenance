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
 * Veículo fictício para demos/postagens — dados inventados, sem relação com veículos reais.
 */
class DemoReferenceVehicleSeeder extends Seeder
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

        $vilaVerde = Workshop::firstOrCreate(
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

        $autoNorte = Workshop::firstOrCreate(
            ['name' => 'AutoCenter Norte'],
            [
                'phone' => '4133551122',
                'whatsapp' => '41988776655',
                'cep' => '81000000',
                'street' => 'Av. das Indústrias',
                'number' => '2200',
                'neighborhood' => 'São Lourenço',
                'city' => 'Curitiba',
                'state' => 'PR',
            ]
        );

        $vehicle = Vehicle::updateOrCreate(
            ['license_plate' => 'XC4D3M0'],
            [
                'renavam' => '55443322109',
                'crv_number' => '112233445566778',
                'brand' => 'Volvo',
                'model' => 'XC40 T4',
                'year' => 2021,
                'color' => 'Azul Denim',
                'chassis' => 'YV1XZEDVOM1234567',
                'engine' => 'B4204T231234567',
                'motorization' => '190CV',
                'odometer_at_registration' => 58_200,
                'current_kilometers' => 94_300,
            ]
        );

        Maintenance::where('vehicle_id', $vehicle->id)->each(function (Maintenance $maintenance): void {
            $maintenance->items()->delete();
            $maintenance->delete();
        });

        $user->vehicles()->syncWithoutDetaching([
            $vehicle->id => [
                'purchase_date' => '2023-09-18',
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
                'maintenance_type' => 'Inspeção pós-aquisição',
                'maintenance_date' => '2024-09-22',
                'kilometers' => 58_200,
                'workshop_id' => $vilaVerde->id,
                'workshop_name' => 'Mecânica Vila Verde',
                'service_category' => 'mechanical',
                'description' => 'Checklist completo após compra do seminovo, com leitura de códigos e teste de rodagem.',
                'items' => [
                    ['name' => 'Scanner diagnóstico completo', 'quantity' => 1, 'unit_price' => 180.00, 'total_price' => 180.00],
                    ['name' => 'Kit filtro de cabine', 'quantity' => 1, 'unit_price' => 142.50, 'total_price' => 142.50],
                    ['name' => 'Troca de óleo sintético 0W20', 'quantity' => 5, 'unit_price' => 68.00, 'total_price' => 340.00],
                    ['name' => 'Balanceamento e calibragem', 'quantity' => 4, 'unit_price' => 35.00, 'total_price' => 140.00],
                ],
            ],
            [
                'maintenance_type' => 'Suspensão dianteira',
                'maintenance_date' => '2025-03-14',
                'kilometers' => 72_600,
                'workshop_id' => $autoNorte->id,
                'workshop_name' => 'AutoCenter Norte',
                'service_category' => 'suspension',
                'description' => 'Ruído em badéis identificado na dianteira esquerda; substituídos amortecedor e batente.',
                'items' => [
                    ['name' => 'Amortecedor dianteiro esquerdo', 'quantity' => 1, 'unit_price' => 890.00, 'total_price' => 890.00],
                    ['name' => 'Kit batente e coifa', 'quantity' => 1, 'unit_price' => 210.00, 'total_price' => 210.00],
                    ['name' => 'Alinhamento 3D', 'quantity' => 1, 'unit_price' => 160.00, 'total_price' => 160.00],
                ],
            ],
            [
                'maintenance_type' => 'Pacote fluidos e filtros',
                'maintenance_date' => '2025-11-08',
                'kilometers' => 86_900,
                'workshop_id' => $vilaVerde->id,
                'workshop_name' => 'Mecânica Vila Verde',
                'service_category' => 'mechanical',
                'description' => 'Manutenção de fim de ano com fluido de arrefecimento, limpador e filtros.',
                'items' => [
                    ['name' => 'Aditivo arrefecimento longa vida', 'quantity' => 2, 'unit_price' => 89.00, 'total_price' => 178.00],
                    ['name' => 'Filtro de ar motor', 'quantity' => 1, 'unit_price' => 195.00, 'total_price' => 195.00],
                    ['name' => 'Palhetas limpador traseiro', 'quantity' => 1, 'unit_price' => 78.00, 'total_price' => 78.00],
                    ['name' => 'Mão de obra pacote', 'quantity' => 1, 'unit_price' => 420.00, 'total_price' => 420.00],
                ],
            ],
            [
                'maintenance_type' => 'Injeção / partida fria',
                'maintenance_date' => '2026-06-02',
                'kilometers' => 94_300,
                'workshop_id' => $autoNorte->id,
                'workshop_name' => 'AutoCenter Norte',
                'service_category' => 'electrical',
                'description' => 'Dificuldade de partida em manhãs frias; limpeza de bicos e atualização de software do módulo.',
                'items' => [
                    ['name' => 'Limpeza ultrassônica bicos', 'quantity' => 4, 'unit_price' => 95.00, 'total_price' => 380.00],
                    ['name' => 'Atualização software ECU', 'quantity' => 1, 'unit_price' => 320.00, 'total_price' => 320.00],
                    ['name' => 'Vela de ignição iridium', 'quantity' => 4, 'unit_price' => 112.00, 'total_price' => 448.00],
                ],
            ],
        ];

        foreach ($maintenances as $payload) {
            $items = $payload['items'];
            unset($payload['items']);

            $maintenance = Maintenance::create(array_merge($payload, [
                'vehicle_id' => $vehicle->id,
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
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

        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());

        $this->command?->info('Veículo demo XC4D3M0 (Volvo XC40 T4) vinculado a '.$user->email);
        $this->command?->info('4 manutenções fictícias — odômetro 94.300 km');
    }
}

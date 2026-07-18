<?php

namespace Database\Seeders;

use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Services\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FelipeVehicleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'fgoncalves2008@gmail.com'],
            [
                'name' => 'Felipe Gonçalves',
                'password' => Hash::make('password123'),
                'user_type' => 'user',
                'is_admin' => true,
                'phone' => '43988776446',
                'country' => 'Brasil',
            ]
        );

        if (! $user->is_admin) {
            $user->update(['is_admin' => true]);
        }

        if (! $user->tenant_id) {
            (new TenantService())->createForUser($user);
            $user->refresh();
        }

        $workshop = Workshop::firstOrCreate(
            ['name' => 'Mercedes-Benz DIVESA Londrina'],
            [
                'tenant_id' => null,
                'user_id' => null,
                'phone' => '4332941800',
                'whatsapp' => '4332941800',
                'email' => null,
                'cep' => '86072000',
                'street' => 'AV TIRADENTES',
                'number' => '4555',
                'complement' => null,
                'neighborhood' => 'JD ROSICLER',
                'city' => 'Londrina',
                'state' => 'PR',
            ]
        );

        $vehicle = Vehicle::firstOrCreate(
            ['license_plate' => 'QOS6H54'],
            [
                'renavam' => '01159110473',
                'crv_number' => '244043259050',
                'brand' => 'Mercedes Benz',
                'model' => 'C 180',
                'year' => 2018,
                'color' => 'PRETA',
                'chassis' => '9BMWF4AW9JM008903',
                'engine' => '27491031429700',
            ]
        );

        if (! $user->vehicles()->where('vehicle_id', $vehicle->id)->exists()) {
            $user->vehicles()->attach($vehicle->id, [
                'purchase_date' => '2020-01-03',
                'is_current_owner' => true,
                'tenant_id' => $user->tenant_id,
                'ownership_verified_at' => now(),
                'crlv_exercise_year' => 2025,
                'owner_document' => '03773399111',
                'ownership_type' => 'owner',
            ]);
        } else {
            $user->vehicles()->updateExistingPivot($vehicle->id, [
                'is_current_owner' => true,
                'tenant_id' => $user->tenant_id,
            ]);
        }

        $maintenance = Maintenance::where('vehicle_id', $vehicle->id)
            ->where('maintenance_type', 'Revisão B (Assyst B)')
            ->whereDate('maintenance_date', '2026-03-10')
            ->first();

        $maintenanceData = [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $workshop->id,
            'workshop_name' => 'Mercedes-Benz DIVESA Londrina',
            'description' => "Revisão B realizada na concessionária DIVESA Londrina.\n\nServiços executados:\n- Assyst B, volume de serviço executar\n- Complemento filtro contra poeira, substituir\n- Complemento elemento filtro de ar, substituir\n- Fluído de freio, substituir\n- Teste de entrada, executar\n- Bateria 80AH 12V AGM, substituir após diagnóstico\n\nResponsável: Jackson Luiz Pereira\nOrçamento interno #4488",
            'kilometers' => 95000,
            'service_category' => 'mechanical',
            'is_manufacturer_required' => true,
        ];

        if ($maintenance) {
            $maintenance->update($maintenanceData);
        } else {
            $maintenance = Maintenance::create(array_merge($maintenanceData, [
                'vehicle_id' => $vehicle->id,
                'maintenance_date' => '2026-03-10',
                'maintenance_type' => 'Revisão B (Assyst B)',
            ]));
        }

        $items = [
            ['name' => 'Filtro de poeira (complemento)', 'quantity' => 1, 'unit_price' => 258.32, 'total_price' => 258.32],
            ['name' => 'Jogo de peças, elemento filtro', 'quantity' => 1, 'unit_price' => 157.68, 'total_price' => 157.68],
            ['name' => 'Lubrificante 229.51 5W30', 'quantity' => 7, 'unit_price' => 85.00, 'total_price' => 595.00],
            ['name' => 'Bateria 12V AGM 80AH', 'quantity' => 1, 'unit_price' => 2248.00, 'total_price' => 2248.00],
            ['name' => 'Elemento do filtro de ar', 'quantity' => 1, 'unit_price' => 436.00, 'total_price' => 436.00],
            ['name' => 'Elemento do filtro de ar do habitáculo', 'quantity' => 1, 'unit_price' => 365.00, 'total_price' => 365.00],
            ['name' => 'Fluido de freio DOT4 Plus', 'quantity' => 2, 'unit_price' => 55.00, 'total_price' => 110.00],
        ];

        $maintenance->items()->delete();

        foreach ($items as $item) {
            MaintenanceItem::create(array_merge($item, [
                'maintenance_id' => $maintenance->id,
                'description' => null,
                'part_number' => null,
            ]));
        }

        \App\Models\Invoice::updateOrCreate(
            ['maintenance_id' => $maintenance->id, 'invoice_type' => 'general'],
            [
                'file_path' => 'invoices/divesa_revisao_b_qos6h54.pdf',
                'file_name' => 'SR Felipe.pdf',
                'invoice_number' => '4488',
                'invoice_date' => '2026-03-10',
                'total_amount' => 5255.00,
            ]
        );

        $this->command?->info("Usuário: {$user->email} / senha: password123");
        $this->command?->info("Veículo: {$vehicle->brand} {$vehicle->model} ({$vehicle->license_plate})");
        $this->command?->info("Manutenção: Revisão B DIVESA em 10/03/2026");
    }
}

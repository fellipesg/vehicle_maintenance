<?php

namespace Database\Seeders;

use App\Enums\WorkshopReviewStatus;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria manutenções pendentes de validação para testar a fila "Validações" da oficina.
 *
 * Proprietário: fgoncalves2008@gmail.com (user_id=3)
 * Oficina alvo: Dev Oficina (workshop_id=6)
 *
 * Roda com: php artisan db:seed --class=WorkshopPendingReviewSeeder
 */
class WorkshopPendingReviewSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'fgoncalves2008@gmail.com')->firstOrFail();
        $workshop = Workshop::findOrFail(6);

        // Veículos atuais do proprietário
        $vehicleIds = DB::table('user_vehicles')
            ->where('user_id', $owner->id)
            ->where('is_current_owner', true)
            ->pluck('vehicle_id')
            ->take(4);

        $services = [
            [
                'maintenance_type' => 'Troca de óleo e filtro',
                'description' => 'Óleo 5W-30 sintético + filtro de óleo. Veículo apresentava barulho leve na partida a frio.',
                'kilometers' => 82000,
                'service_category' => 'mechanical',
                'maintenance_date' => now()->subDays(3)->toDateString(),
            ],
            [
                'maintenance_type' => 'Revisão dos 90.000 km',
                'description' => 'Revisão completa: velas, filtros, fluido de freio, correia acessórios e alinhamento.',
                'kilometers' => 91200,
                'service_category' => 'mechanical',
                'maintenance_date' => now()->subDays(7)->toDateString(),
            ],
            [
                'maintenance_type' => 'Pastilhas e discos de freio dianteiros',
                'description' => 'Pastilhas Bosch e discos originais. Barulho ao frear acima de 80 km/h eliminado.',
                'kilometers' => 78500,
                'service_category' => 'mechanical',
                'maintenance_date' => now()->subDays(14)->toDateString(),
            ],
            [
                'maintenance_type' => 'Diagnóstico elétrico — check engine',
                'description' => 'Leitura de falhas: P0171 (mistura pobre). Limpeza do sensor MAF e injetores.',
                'kilometers' => 65000,
                'service_category' => 'electrical',
                'maintenance_date' => now()->subDays(21)->toDateString(),
            ],
        ];

        foreach ($vehicleIds as $index => $vehicleId) {
            $service = $services[$index] ?? $services[0];

            Maintenance::create(array_merge([
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'vehicle_id' => $vehicleId,
                'workshop_id' => $workshop->id,
                'workshop_name' => $workshop->name,
                'workshop_review_status' => WorkshopReviewStatus::Pending,
                'workshop_review_requested_at' => now(),
                'verified_at' => null,
                'is_manufacturer_required' => false,
            ], $service));
        }

        $count = $vehicleIds->count();
        $this->command->info("Criadas {$count} manutencoes pendentes para a oficina \"{$workshop->name}\" (W{$workshop->id}).");
    }
}

<?php

namespace Database\Seeders\Concerns;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\DemoMaintenanceWarrantiesSeeder;
use Database\Seeders\DemoWorkshopAccountsSeeder;

trait ResolvesDemoWorkshops
{
    protected function resolveDemoWorkshop(string $officialName, ?string $email): ?Workshop
    {
        if ($officialName === DemoWorkshopAccountsSeeder::DIVESA_NAME) {
            $maintenance = Maintenance::query()
                ->whereHas('vehicle', fn ($query) => $query->where('license_plate', DemoMaintenanceWarrantiesSeeder::PLATE))
                ->where('maintenance_type', 'Revisão B (Assyst B)')
                ->whereDate('maintenance_date', '2026-03-10')
                ->first();

            if ($maintenance?->workshop !== null) {
                return $maintenance->workshop;
            }
        }

        if (is_string($email) && $email !== '') {
            $user = User::query()->where('email', $email)->first();

            if ($user?->workshop !== null) {
                return $user->workshop;
            }
        }

        return Workshop::query()
            ->where('name', $officialName)
            ->orderBy('id')
            ->first();
    }
}

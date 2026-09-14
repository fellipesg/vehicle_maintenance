<?php

namespace Database\Seeders;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Workshop;
use App\Services\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoWorkshopAccountsSeeder extends Seeder
{
    public const PASSWORD = 'password123';

    public const DIVESA_EMAIL = 'divesa@vehicle-maintenance.test';

    public const BROTHERS_EMAIL = 'brothers@vehicle-maintenance.test';

    public const DIVESA_NAME = 'Mercedes-Benz DIVESA Londrina';

    public const BROTHERS_NAME = 'Brothers Auto Service';

    public const DEV_WORKSHOP_EMAIL = DevPortalUsersSeeder::WORKSHOP_EMAIL;

    public const DEV_WORKSHOP_NAME = 'Dev Oficina';

    /**
     * @return list<array{email: string, name: string, workshop_pattern: string, official_name: string}>
     */
    public static function accounts(): array
    {
        return [
            [
                'email' => self::DIVESA_EMAIL,
                'name' => 'DIVESA Oficina',
                'workshop_pattern' => '%DIVESA%',
                'official_name' => self::DIVESA_NAME,
            ],
            [
                'email' => self::BROTHERS_EMAIL,
                'name' => 'Brothers Oficina',
                'workshop_pattern' => '%Brothers Auto%',
                'official_name' => self::BROTHERS_NAME,
            ],
        ];
    }

    public function run(): void
    {
        if (! $this->shouldRun()) {
            $this->command?->warn('DemoWorkshopAccountsSeeder skipped for this environment.');

            return;
        }

        $tenantService = new TenantService;
        $divesaId = null;
        $brothersId = null;

        foreach (self::accounts() as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => self::PASSWORD,
                    'user_type' => 'workshop',
                    'is_admin' => false,
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->tenant_id) {
                $tenantService->createForUser($user);
                $user->refresh();
            }

            $workshop = $this->findWorkshopByNamePattern($account['workshop_pattern']);

            if (! $workshop) {
                $this->command?->warn("Workshop not found for pattern {$account['workshop_pattern']}");

                continue;
            }

            $workshop->update([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'email' => $account['email'],
                'name' => $account['official_name'],
            ]);

            if ($account['email'] === self::DIVESA_EMAIL) {
                $divesaId = $workshop->id;
            }

            if ($account['email'] === self::BROTHERS_EMAIL) {
                $brothersId = $workshop->id;

                $this->assignMaintenancesByWorkshopName('%Brothers%', $workshop->id, self::BROTHERS_NAME);
            }
        }

        $devWorkshop = $this->resolveDevWorkshop();

        if ($devWorkshop && $divesaId && $brothersId) {
            $this->assignOrphansToDevWorkshop($devWorkshop->id, $divesaId, $brothersId);
        }

        $this->command?->info('Demo workshop accounts ready (password: '.self::PASSWORD.')');
        $this->printConferenceCounts($divesaId, $brothersId, $devWorkshop?->id);
    }

    public function shouldRun(): bool
    {
        return app()->environment('local', 'testing')
            || (bool) config('database.demo_workshop_accounts', false);
    }

    private function resolveDevWorkshop(): ?Workshop
    {
        $devUser = User::query()->where('email', self::DEV_WORKSHOP_EMAIL)->first();

        if ($devUser?->workshop) {
            return $devUser->workshop;
        }

        return Workshop::query()
            ->where('name', self::DEV_WORKSHOP_NAME)
            ->orWhere('id', 6)
            ->first();
    }

    private function printConferenceCounts(?int $divesaId, ?int $brothersId, ?int $devId): void
    {
        $rows = DB::table('maintenances')
            ->selectRaw('workshop_id, workshop_name, COUNT(*) as total')
            ->groupBy('workshop_id', 'workshop_name')
            ->orderByDesc('total')
            ->get();

        $this->command?->line('');
        $this->command?->info('Maintenance counts by workshop after assign:');

        foreach ($rows as $row) {
            $label = match ((int) $row->workshop_id) {
                $divesaId => 'DIVESA',
                $brothersId => 'Brothers',
                $devId => 'Dev Oficina',
                default => 'other',
            };
            $this->command?->line("  [{$label}] workshop_id={$row->workshop_id} {$row->workshop_name}: {$row->total}");
        }
    }

    private function findWorkshopByNamePattern(string $pattern): ?Workshop
    {
        $query = Workshop::query();

        if (DB::getDriverName() === 'pgsql') {
            $query->whereRaw('name ILIKE ?', [$pattern]);
        } else {
            $query->whereRaw('LOWER(name) LIKE LOWER(?)', [$pattern]);
        }

        return $query->first();
    }

    private function assignMaintenancesByWorkshopName(string $pattern, int $workshopId, string $officialName): void
    {
        $query = Maintenance::query();

        if (DB::getDriverName() === 'pgsql') {
            $query->whereRaw('workshop_name ILIKE ?', [$pattern]);
        } else {
            $query->whereRaw('LOWER(workshop_name) LIKE LOWER(?)', [$pattern]);
        }

        $query->update([
            'workshop_id' => $workshopId,
            'workshop_name' => $officialName,
        ]);
    }

    private function assignOrphansToDevWorkshop(int $devId, int $divesaId, int $brothersId): void
    {
        $query = Maintenance::query()
            ->where(function ($builder) use ($divesaId, $brothersId) {
                $builder->whereNull('workshop_id')
                    ->orWhereNotIn('workshop_id', [$divesaId, $brothersId]);
            });

        if (DB::getDriverName() === 'pgsql') {
            $query->whereRaw('workshop_name NOT ILIKE ?', ['%DIVESA%'])
                ->whereRaw('workshop_name NOT ILIKE ?', ['%Brothers%']);
        } else {
            $query->whereRaw('LOWER(workshop_name) NOT LIKE LOWER(?)', ['%DIVESA%'])
                ->whereRaw('LOWER(workshop_name) NOT LIKE LOWER(?)', ['%Brothers%']);
        }

        $query->update([
            'workshop_id' => $devId,
            'workshop_name' => self::DEV_WORKSHOP_NAME,
        ]);
    }
}

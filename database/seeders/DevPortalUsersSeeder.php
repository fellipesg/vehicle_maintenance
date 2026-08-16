<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\TenantService;
use Illuminate\Database\Seeder;

class DevPortalUsersSeeder extends Seeder
{
    public const PASSWORD = 'password123';

    public const OWNER_EMAIL = 'owner@vehicle-maintenance.test';

    public const GARAGE_EMAIL = 'loja@vehicle-maintenance.test';

    public const ADMIN_EMAIL = 'admin@vehicle-maintenance.test';

    /**
     * @return list<array{email: string, name: string, user_type: string, is_admin: bool, login_path: string}>
     */
    public static function accounts(): array
    {
        return [
            [
                'email' => self::OWNER_EMAIL,
                'name' => 'Dev Proprietário',
                'user_type' => 'user',
                'is_admin' => false,
                'login_path' => '/login/usuario',
            ],
            [
                'email' => self::GARAGE_EMAIL,
                'name' => 'Dev Lojista',
                'user_type' => 'garage',
                'is_admin' => false,
                'login_path' => '/login/lojista',
            ],
            [
                'email' => self::ADMIN_EMAIL,
                'name' => 'Dev Admin',
                'user_type' => 'user',
                'is_admin' => true,
                'login_path' => '/login/admin',
            ],
        ];
    }

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DevPortalUsersSeeder skipped: only runs in local/testing environments.');

            return;
        }

        $tenantService = new TenantService;

        foreach (self::accounts() as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => self::PASSWORD,
                    'user_type' => $account['user_type'],
                    'is_admin' => $account['is_admin'],
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->tenant_id) {
                $tenantService->createForUser($user);
                $user->refresh();
            }
        }

        $this->command?->info('Dev portal users ready (password: '.self::PASSWORD.'):');

        foreach (self::accounts() as $account) {
            $this->command?->line("  {$account['email']} — {$account['login_path']}");
        }

        // One-liner: php artisan db:seed --class=DevPortalUsersSeeder
    }
}

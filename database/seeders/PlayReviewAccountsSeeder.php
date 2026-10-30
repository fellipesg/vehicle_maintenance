<?php

namespace Database\Seeders;

use App\Enums\GarageStatus;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Maintenance\MaintenanceVerificationStamper;
use App\Services\TenantService;
use App\Services\Vehicle\VehicleMileageService;
use App\Services\Vehicle\VehiclePlateHistoryService;
use Illuminate\Database\Seeder;

/**
 * Contas permanentes entregues ao revisor do Google Play em "Detalhes do login".
 *
 * Diferente do DevPortalUsersSeeder, este roda em produção: a análise da loja
 * precisa de contas reais, já populadas e sem 2FA.
 *
 * O hub de login do app abre só proprietário e lojista (LoginPortal.showInHub) —
 * são essas as duas contas a declarar no Play Console. A conta de oficina existe
 * para selar as manutenções do proprietário (procedência) e para o dia em que o
 * portal da oficina voltar ao hub. O painel admin não existe no app.
 *
 * A senha nunca mora no repositório: vem de PLAY_REVIEW_PASSWORD.
 *
 * Uso: php artisan db:seed --class=PlayReviewAccountsSeeder --force
 */
class PlayReviewAccountsSeeder extends Seeder
{
    public const OWNER_EMAIL = 'review.owner@revisalog.com.br';

    public const GARAGE_EMAIL = 'review.dealer@revisalog.com.br';

    public const WORKSHOP_EMAIL = 'review.workshop@revisalog.com.br';

    public const OWNER_PLATE = 'RVL0A01';

    public const GARAGE_PLATE = 'RVL0B02';

    /**
     * Contas declaradas no Play Console, uma por portal do app.
     *
     * @return list<array{email: string, name: string, user_type: string, portal: string}>
     */
    public static function accounts(): array
    {
        return [
            [
                'email' => self::OWNER_EMAIL,
                'name' => 'Play Review Proprietário',
                'user_type' => 'user',
                'portal' => 'usuario',
            ],
            [
                'email' => self::GARAGE_EMAIL,
                'name' => 'Play Review Lojista',
                'user_type' => 'garage',
                'portal' => 'lojista',
            ],
            [
                'email' => self::WORKSHOP_EMAIL,
                'name' => 'Play Review Oficina',
                'user_type' => 'workshop',
                'portal' => 'oficina',
            ],
        ];
    }

    public function run(): void
    {
        $password = (string) config('services.play_review.password');

        if ($password === '') {
            $this->command?->error('PLAY_REVIEW_PASSWORD não definida: nenhuma conta de revisão foi criada.');

            return;
        }

        $tenantService = new TenantService;
        $users = [];

        foreach (self::accounts() as $account) {
            $users[$account['portal']] = $this->upsertAccount($account, $password, $tenantService);
        }

        $this->approveReviewGarage($users['lojista']);
        $this->seedOwnerHistory($users['usuario'], $users['oficina']);
        $this->seedGarageStock($users['lojista']);

        $this->command?->info('Contas de revisão do Google Play prontas:');

        foreach (self::accounts() as $account) {
            $this->command?->line("  {$account['email']} — portal {$account['portal']}");
        }
    }

    /**
     * @param  array{email: string, name: string, user_type: string, portal: string}  $account
     */
    private function upsertAccount(array $account, string $password, TenantService $tenantService): User
    {
        $user = User::updateOrCreate(
            ['email' => $account['email']],
            [
                'name' => $account['name'],
                'password' => $password,
                'user_type' => $account['user_type'],
                'is_admin' => false,
            ]
        );

        // O revisor não tem como responder a um desafio de 2FA nem a uma
        // verificação de e-mail, então essas contas nascem e permanecem livres
        // das duas. Campos fora do $fillable exigem forceFill.
        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        if (! $user->tenant_id) {
            $tenantService->createForUser($user);
            $user->refresh();
        }

        return $user;
    }

    /**
     * A loja de revisão nasce aprovada. Cadastro em análise só abre "Minha loja"
     * (App\Http\Middleware\EnsureGarageApproved), e o revisor não tem como esperar
     * uma análise nem a quem pedir.
     */
    private function approveReviewGarage(User $garage): void
    {
        $garage->loadMissing('garage');

        $garage->garage?->forceFill([
            'status' => GarageStatus::Approved,
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * Veículo do proprietário com histórico: duas ordens de serviço seladas pela
     * oficina de revisão (que assim também abre com conteúdo) e uma declarada.
     */
    private function seedOwnerHistory(User $owner, User $workshopUser): void
    {
        $workshopUser->loadMissing('workshop');
        $workshop = $workshopUser->workshop;

        $vehicle = Vehicle::updateOrCreate(
            ['license_plate' => self::OWNER_PLATE],
            [
                'renavam' => '10293847560',
                'brand' => 'Honda',
                'model' => 'Civic EXL',
                'year' => 2021,
                'color' => 'Prata',
                'chassis' => '9BRVLDEMO00000001',
                'motorization' => '2.0 16V',
                'odometer_at_registration' => 42_000,
                'current_kilometers' => 71_400,
            ]
        );

        $this->resetMaintenances($vehicle);

        $owner->vehicles()->syncWithoutDetaching([
            $vehicle->id => [
                'purchase_date' => '2024-11-05',
                'is_current_owner' => true,
                'tenant_id' => $owner->tenant_id,
                'ownership_verified_at' => now(),
                'ownership_type' => 'owner',
                'terms_accepted_at' => now(),
                'terms_version' => (string) config('legal.terms_version', '1'),
            ],
        ]);

        $services = [
            [
                'maintenance_type' => 'Revisão de 50.000 km',
                'maintenance_date' => '2025-04-12',
                'kilometers' => 48_300,
                'service_category' => 'mechanical',
                'description' => 'Revisão programada de fábrica com troca de fluidos e filtros.',
                'sealed' => true,
                'items' => [
                    ['name' => 'Óleo sintético 5W30', 'quantity' => 4, 'unit_price' => 72.00, 'total_price' => 288.00],
                    ['name' => 'Filtro de óleo', 'quantity' => 1, 'unit_price' => 68.00, 'total_price' => 68.00],
                    ['name' => 'Filtro de ar do motor', 'quantity' => 1, 'unit_price' => 145.00, 'total_price' => 145.00],
                    ['name' => 'Mão de obra da revisão', 'quantity' => 1, 'unit_price' => 320.00, 'total_price' => 320.00],
                ],
            ],
            [
                'maintenance_type' => 'Freios e suspensão',
                'maintenance_date' => '2025-11-20',
                'kilometers' => 60_750,
                'service_category' => 'suspension',
                'description' => 'Pastilhas dianteiras no limite e ruído na suspensão traseira.',
                'sealed' => true,
                'items' => [
                    ['name' => 'Jogo de pastilhas dianteiras', 'quantity' => 1, 'unit_price' => 430.00, 'total_price' => 430.00],
                    ['name' => 'Amortecedor traseiro', 'quantity' => 2, 'unit_price' => 560.00, 'total_price' => 1120.00],
                    ['name' => 'Alinhamento e balanceamento', 'quantity' => 1, 'unit_price' => 180.00, 'total_price' => 180.00],
                ],
            ],
            [
                'maintenance_type' => 'Troca de óleo (declarada)',
                'maintenance_date' => '2026-06-18',
                'kilometers' => 71_400,
                'service_category' => 'mechanical',
                'description' => 'Troca feita em oficina de bairro e registrada pelo proprietário.',
                'sealed' => false,
                'workshop_name' => 'Oficina do bairro (não cadastrada)',
                'items' => [
                    ['name' => 'Óleo sintético 5W30', 'quantity' => 4, 'unit_price' => 75.00, 'total_price' => 300.00],
                ],
            ],
        ];

        $stamper = app(MaintenanceVerificationStamper::class);

        foreach ($services as $service) {
            $items = $service['items'];
            $sealed = $service['sealed'];
            unset($service['items'], $service['sealed']);

            $maintenance = Maintenance::create(array_merge($service, [
                'vehicle_id' => $vehicle->id,
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'workshop_id' => $sealed ? $workshop?->id : null,
                'workshop_name' => $sealed ? $workshop?->name : ($service['workshop_name'] ?? null),
                'is_manufacturer_required' => false,
            ]));

            foreach ($items as $item) {
                MaintenanceItem::create(array_merge($item, [
                    'maintenance_id' => $maintenance->id,
                ]));
            }

            $stamper->stamp($maintenance, $sealed && $workshop !== null ? $workshopUser : $owner);
        }

        app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());
        app(VehiclePlateHistoryService::class)->recordInitialPlate($vehicle->fresh(), 'seed', $owner);
    }

    /**
     * Um veículo em estoque para o portal do lojista não abrir vazio.
     */
    private function seedGarageStock(User $garage): void
    {
        $vehicle = Vehicle::updateOrCreate(
            ['license_plate' => self::GARAGE_PLATE],
            [
                'renavam' => '20394857610',
                'brand' => 'Toyota',
                'model' => 'Corolla XEI',
                'year' => 2022,
                'color' => 'Branco',
                'chassis' => '9BRVLDEMO00000002',
                'motorization' => '2.0 Dynamic Force',
                'odometer_at_registration' => 38_500,
                'current_kilometers' => 38_500,
            ]
        );

        $garage->vehicles()->syncWithoutDetaching([
            $vehicle->id => [
                'purchase_date' => '2026-08-14',
                'is_current_owner' => true,
                'tenant_id' => $garage->tenant_id,
                'ownership_verified_at' => now(),
                'ownership_type' => 'owner',
                'terms_accepted_at' => now(),
                'terms_version' => (string) config('legal.terms_version', '1'),
            ],
        ]);

        app(VehiclePlateHistoryService::class)->recordInitialPlate($vehicle->fresh(), 'seed', $garage);
    }

    /**
     * Manutenções são recriadas a cada execução para o seeder ficar idempotente.
     */
    private function resetMaintenances(Vehicle $vehicle): void
    {
        Maintenance::where('vehicle_id', $vehicle->id)->each(function (Maintenance $maintenance): void {
            $maintenance->items()->delete();
            $maintenance->delete();
        });
    }
}

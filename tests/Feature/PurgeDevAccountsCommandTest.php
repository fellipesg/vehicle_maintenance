<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\WorkshopMessageOptOut;
use Database\Seeders\DevPortalUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Limpeza das contas do DevPortalUsersSeeder que vazaram para produção: a senha está no
 * repositório e uma delas é admin.
 *
 * O comando fecha a credencial e anonimiza, mas **nunca apaga a linha**. Dez tabelas apontam para
 * `users` em cascata, e duas delas doem de verdade: a declaração da consignação, que guarda a
 * trilha de autorização do proprietário, e o opt-out de mensagens, cuja remoção faria a pessoa
 * voltar a receber contato.
 */
class PurgeDevAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function devUser(string $email, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => $email,
            'password' => DevPortalUsersSeeder::PASSWORD,
        ], $attributes));
    }

    public function test_it_strips_the_admin_flag_and_the_known_password(): void
    {
        $admin = $this->devUser('admin@vehicle-maintenance.test', ['is_admin' => true]);

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $admin->refresh();

        $this->assertFalse($admin->isAdmin());
        $this->assertFalse(Hash::check(DevPortalUsersSeeder::PASSWORD, $admin->password));
        $this->assertStringEndsWith('@deleted.revisalog.invalid', $admin->email);
    }

    public function test_it_revokes_tokens(): void
    {
        $user = $this->devUser('loja@vehicle-maintenance.test');
        $user->createToken('dev');

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_never_deletes_the_row_even_when_nothing_is_attached(): void
    {
        $user = $this->devUser('owner@vehicle-maintenance.test');

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame('Conta excluída', $user->fresh()->name);
    }

    public function test_it_keeps_the_maintenance_history(): void
    {
        $user = $this->devUser('oficina@vehicle-maintenance.test');
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $this->assertModelExists($maintenance->fresh());
        $this->assertModelExists($vehicle->fresh());
    }

    public function test_it_keeps_a_consignment_declared_by_the_dev_garage(): void
    {
        $garage = $this->devUser('loja@vehicle-maintenance.test', ['user_type' => 'garage']);

        $consignment = VehicleConsignment::query()->create([
            'vehicle_id' => Vehicle::factory()->create()->id,
            'garage_user_id' => $garage->id,
            'owner_name' => 'Dono de verdade',
            'declaration_accepted_at' => now(),
            'declaration_ip' => '203.0.113.10',
            'status' => VehicleConsignment::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        // vehicle_consignments.garage_user_id é cascadeOnDelete: um delete() apagaria a
        // declaração inteira, com a trilha de autorização do proprietário.
        $this->assertModelExists($consignment->fresh());
        $this->assertSame('203.0.113.10', $consignment->fresh()->declaration_ip);
    }

    public function test_it_keeps_a_message_opt_out(): void
    {
        $user = $this->devUser('owner@vehicle-maintenance.test');

        $optOut = WorkshopMessageOptOut::query()->create(['user_id' => $user->id]);

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        // Apagar um opt-out devolveria a pessoa à lista de contato.
        $this->assertModelExists($optOut->fresh());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $user = $this->devUser('owner@vehicle-maintenance.test', ['is_admin' => true]);

        $this->artisan('users:purge-dev-accounts', ['--dry-run' => true])->assertSuccessful();

        $user->refresh();

        $this->assertTrue($user->isAdmin());
        $this->assertSame('owner@vehicle-maintenance.test', $user->email);
        $this->assertTrue(Hash::check(DevPortalUsersSeeder::PASSWORD, $user->password));
    }

    public function test_it_never_touches_a_real_account(): void
    {
        $real = User::factory()->create([
            'email' => 'cliente@gmail.com',
            'name' => 'Cliente de verdade',
        ]);

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $real->refresh();

        $this->assertSame('Cliente de verdade', $real->name);
        $this->assertSame('cliente@gmail.com', $real->email);
    }

    public function test_it_does_not_match_a_lookalike_domain(): void
    {
        $lookalike = User::factory()->create(['email' => 'alguem@vehicle-maintenance.test.br']);

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $this->assertSame('alguem@vehicle-maintenance.test.br', $lookalike->fresh()->email);
    }

    public function test_it_is_safe_to_run_when_there_is_nothing_to_purge(): void
    {
        User::factory()->create(['email' => 'cliente@gmail.com']);

        $this->artisan('users:purge-dev-accounts')
            ->expectsOutputToContain('Nenhuma conta de desenvolvimento encontrada.')
            ->assertSuccessful();
    }

    public function test_it_handles_every_account_the_dev_seeder_creates(): void
    {
        foreach (DevPortalUsersSeeder::accounts() as $account) {
            $this->devUser($account['email'], ['is_admin' => $account['is_admin']]);
        }

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $this->assertSame(0, User::query()->where('email', 'like', '%@vehicle-maintenance.test')->count());
        $this->assertSame(0, User::query()->where('is_admin', true)->count());
    }
}

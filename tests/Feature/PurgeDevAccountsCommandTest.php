<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DevPortalUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Limpeza das contas do DevPortalUsersSeeder que vazaram para produção: a senha está no
 * repositório e uma delas é admin. O comando fecha a credencial sempre, remove a conta vazia e
 * anonimiza a que já tem histórico — apagar a linha cascatearia em maintenances.user_id.
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

    public function test_it_removes_a_dev_account_with_nothing_attached(): void
    {
        $user = $this->devUser('owner@vehicle-maintenance.test');

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_it_strips_the_admin_flag_and_the_known_password(): void
    {
        $admin = $this->devUser('admin@vehicle-maintenance.test', ['is_admin' => true]);
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($admin, $vehicle);

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $admin->refresh();

        $this->assertFalse($admin->isAdmin());
        $this->assertFalse(Hash::check(DevPortalUsersSeeder::PASSWORD, $admin->password));
    }

    public function test_it_revokes_tokens_of_a_dev_account(): void
    {
        $user = $this->devUser('loja@vehicle-maintenance.test');
        $user->createToken('dev');

        $this->assertSame(1, $user->tokens()->count());

        $this->artisan('users:purge-dev-accounts')->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_anonymizes_instead_of_deleting_when_there_is_history(): void
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

        // A linha fica, anonimizada: apagá-la levaria a manutenção junto.
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertModelExists($maintenance->fresh());

        $user->refresh();
        $this->assertSame('Conta excluída', $user->name);
        $this->assertStringEndsWith('@deleted.revisalog.invalid', $user->email);
        $this->assertFalse(Hash::check(DevPortalUsersSeeder::PASSWORD, $user->password));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $user = $this->devUser('owner@vehicle-maintenance.test', ['is_admin' => true]);

        $this->artisan('users:purge-dev-accounts', ['--dry-run' => true])->assertSuccessful();

        $user->refresh();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertTrue($user->isAdmin());
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

    public function test_it_is_safe_to_run_when_there_is_nothing_to_purge(): void
    {
        User::factory()->create(['email' => 'cliente@gmail.com']);

        $this->artisan('users:purge-dev-accounts')
            ->expectsOutputToContain('Nenhuma conta de desenvolvimento encontrada.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
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

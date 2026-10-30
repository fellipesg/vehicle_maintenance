<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\PlayReviewAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlayReviewAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'play-review-secret-123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.play_review.password' => self::PASSWORD]);
    }

    public function test_creates_one_account_per_portal_and_is_idempotent(): void
    {
        $seeder = new PlayReviewAccountsSeeder;

        $seeder->run();
        $this->assertSame(3, User::count());

        $seeder->run();
        $this->assertSame(3, User::count());
        $this->assertSame(2, Vehicle::count());

        $owner = User::where('email', PlayReviewAccountsSeeder::OWNER_EMAIL)->firstOrFail();
        $garage = User::where('email', PlayReviewAccountsSeeder::GARAGE_EMAIL)->firstOrFail();
        $workshop = User::where('email', PlayReviewAccountsSeeder::WORKSHOP_EMAIL)->firstOrFail();

        $this->assertTrue($owner->isUser());
        $this->assertNotNull($owner->tenant_id);

        $this->assertTrue($garage->isGarage());
        $this->assertNotNull($garage->garage);

        $this->assertTrue($workshop->isWorkshop());
        $this->assertNotNull($workshop->workshop);

        foreach ([$owner, $garage, $workshop] as $user) {
            $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        }
    }

    public function test_accounts_cannot_be_blocked_by_two_factor_or_email_verification(): void
    {
        (new PlayReviewAccountsSeeder)->run();

        foreach (PlayReviewAccountsSeeder::accounts() as $account) {
            $user = User::where('email', $account['email'])->firstOrFail();

            $this->assertFalse($user->isAdmin(), "{$account['email']} não pode ser admin");
            $this->assertNull($user->two_factor_secret);
            $this->assertNull($user->two_factor_confirmed_at);
            $this->assertNull($user->two_factor_recovery_codes);
            $this->assertNotNull($user->email_verified_at);
        }
    }

    public function test_two_factor_is_cleared_on_a_later_run(): void
    {
        $seeder = new PlayReviewAccountsSeeder;
        $seeder->run();

        User::where('email', PlayReviewAccountsSeeder::OWNER_EMAIL)->firstOrFail()->forceFill([
            'two_factor_secret' => 'ABC123',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $seeder->run();

        $owner = User::where('email', PlayReviewAccountsSeeder::OWNER_EMAIL)->firstOrFail();
        $this->assertNull($owner->two_factor_secret);
        $this->assertNull($owner->two_factor_confirmed_at);
    }

    public function test_each_account_logs_in_through_its_own_portal(): void
    {
        (new PlayReviewAccountsSeeder)->run();

        foreach (PlayReviewAccountsSeeder::accounts() as $account) {
            $this->postJson('/api/v1/login', [
                'email' => $account['email'],
                'password' => self::PASSWORD,
                'portal' => $account['portal'],
            ])->assertOk()
                ->assertJsonPath('data.user.email', $account['email']);
        }
    }

    public function test_review_account_is_rejected_on_a_portal_it_does_not_own(): void
    {
        (new PlayReviewAccountsSeeder)->run();

        $this->postJson('/api/v1/login', [
            'email' => PlayReviewAccountsSeeder::OWNER_EMAIL,
            'password' => self::PASSWORD,
            'portal' => 'oficina',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account does not have access to this portal.');
    }

    public function test_owner_account_opens_with_vehicle_history(): void
    {
        (new PlayReviewAccountsSeeder)->run();

        $owner = User::where('email', PlayReviewAccountsSeeder::OWNER_EMAIL)->firstOrFail();
        $workshop = User::where('email', PlayReviewAccountsSeeder::WORKSHOP_EMAIL)->firstOrFail();

        $vehicle = $owner->vehicles()->firstOrFail();
        $this->assertSame(PlayReviewAccountsSeeder::OWNER_PLATE, $vehicle->license_plate);

        $maintenances = Maintenance::where('vehicle_id', $vehicle->id)->get();
        $this->assertCount(3, $maintenances);

        $sealed = $maintenances->whereNotNull('verified_at');
        $this->assertCount(2, $sealed, 'A oficina de revisão precisa de ordens de serviço seladas');
        $this->assertTrue($sealed->every(
            fn (Maintenance $maintenance): bool => $maintenance->workshop_id === $workshop->workshop->id
        ));
    }

    public function test_garage_account_opens_with_a_vehicle_in_stock(): void
    {
        (new PlayReviewAccountsSeeder)->run();

        $garage = User::where('email', PlayReviewAccountsSeeder::GARAGE_EMAIL)->firstOrFail();

        $this->assertSame(
            PlayReviewAccountsSeeder::GARAGE_PLATE,
            $garage->vehicles()->firstOrFail()->license_plate
        );
    }

    public function test_review_garage_is_approved_so_the_whole_area_opens(): void
    {
        (new PlayReviewAccountsSeeder)->run();

        $garage = User::where('email', PlayReviewAccountsSeeder::GARAGE_EMAIL)->firstOrFail();

        $this->assertTrue($garage->garage->isApproved());

        // Loja em análise só abriria "Minha loja", e o revisor não tem a quem pedir aprovação.
        $this->actingAs($garage)->get(route('garage.vehicles.index'))->assertOk();
    }

    public function test_review_workshop_is_approved_so_it_can_register_and_seal(): void
    {
        (new PlayReviewAccountsSeeder)->run();

        $workshop = User::where('email', PlayReviewAccountsSeeder::WORKSHOP_EMAIL)->firstOrFail();

        $this->assertTrue($workshop->workshop->isApproved());

        // Oficina em análise só abriria "Minha oficina", e o revisor não tem a quem pedir aprovação.
        $this->actingAs($workshop)->get(route('workshop.maintenances.create'))->assertOk();
    }

    public function test_seeder_creates_nothing_without_a_configured_password(): void
    {
        config(['services.play_review.password' => null]);

        (new PlayReviewAccountsSeeder)->run();

        $this->assertSame(0, User::count());
    }
}

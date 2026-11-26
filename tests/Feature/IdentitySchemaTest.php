<?php

namespace Tests\Feature;

use App\Models\Identity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * O esquema que sustenta a identidade compartilhada. São as garantias que o resto do código assume
 * e que divergem entre SQLite e Postgres (a suíte roda nos dois: phpunit.xml e phpunit.pgsql.xml),
 * justamente onde mexemos em índice único.
 */
class IdentitySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_left_the_users_table(): void
    {
        foreach (['password', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('users', $column),
                "users.{$column} mora em identities agora."
            );
        }

        $this->assertTrue(Schema::hasColumn('users', 'identity_id'));
        $this->assertTrue(Schema::hasColumn('users', 'remember_token'), 'O "Lembrar de mim" é de cada conta.');

        foreach (['email', 'password', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes'] as $column) {
            $this->assertTrue(Schema::hasColumn('identities', $column));
        }
    }

    public function test_the_same_email_can_hold_one_account_per_role(): void
    {
        $identity = Identity::factory()->forEmail('pessoa@example.com')->create();

        foreach (['user', 'garage', 'workshop'] as $userType) {
            User::factory()->state(['user_type' => $userType])->forIdentity($identity)->create();
        }

        $this->assertSame(3, User::query()->where('email', 'pessoa@example.com')->count());
        $this->assertCount(3, $identity->users()->get());
    }

    public function test_the_identity_email_stays_unique(): void
    {
        Identity::factory()->forEmail('pessoa@example.com')->create();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Identity::factory()->forEmail('pessoa@example.com')->create();
    }

    public function test_an_account_cannot_be_saved_without_an_identity(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        // `identity_id` é obrigatório no banco: conta sem identidade não entra por porta nenhuma.
        \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['identity_id' => null]);
    }

    public function test_changing_the_identity_email_changes_every_sibling(): void
    {
        $identity = Identity::factory()->forEmail('antigo@example.com')->create();
        User::factory()->asUser()->forIdentity($identity)->create();
        User::factory()->asWorkshop()->forIdentity($identity)->create();

        $identity->changeEmailTo('novo@example.com');

        $this->assertSame(0, User::query()->where('email', 'antigo@example.com')->count());
        $this->assertSame(2, User::query()->where('email', 'novo@example.com')->count());
        $this->assertDatabaseHas('identities', ['id' => $identity->getKey(), 'email' => 'novo@example.com']);
    }
}

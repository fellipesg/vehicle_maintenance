<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade compartilhada: e-mail, senha e 2FA saem de `users` e passam a morar numa linha de
 * `identities`. Cada papel (proprietário, lojista, oficina) continua sendo sua própria linha de
 * `users`, com o `tenant_id` dela — nada muda na autorização. O que muda é a porta de entrada:
 * um e-mail pode ter uma conta por papel, e elas são contas irmãs sob a mesma identidade.
 *
 * `users.email` fica, desnormalizado (e sem o unique global): são 10 buscas por e-mail no código,
 * `EloquentUserProvider` consulta por ele e toda tela e e-mail lê `users.email`. A fonte da verdade
 * é `identities.email`; quem troca o e-mail troca nos dois lugares (App\Models\Identity::changeEmailTo).
 *
 * O backfill é 1:1: cada usuário de hoje ganha a identidade dele, com o e-mail e a senha que já
 * tinha. `down()` desfaz na ordem inversa, devolvendo senha e 2FA para `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identities', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamps();
        });

        // Uma identidade por usuário existente. users.email é único hoje, então não há colisão.
        DB::statement(<<<'SQL'
            insert into identities (
                email, password, two_factor_secret, two_factor_confirmed_at,
                two_factor_recovery_codes, created_at, updated_at
            )
            select
                email, password, two_factor_secret, two_factor_confirmed_at,
                two_factor_recovery_codes, created_at, updated_at
            from users
        SQL);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('identity_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        DB::statement('update users set identity_id = (select i.id from identities i where i.email = users.email)');

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('identity_id')->nullable(false)->change();
            // Uma conta por papel por identidade: a pessoa não tem dois perfis de proprietário.
            $table->unique(['identity_id', 'user_type']);
        });

        Schema::table('users', function (Blueprint $table) {
            // O e-mail deixa de ser único global (é o unique de identities que faz esse papel),
            // mas continua indexado: é por ele que o provider do guard e as 10 buscas consultam.
            $table->dropUnique('users_email_unique');
            $table->index('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
        });

        DB::statement(<<<'SQL'
            update users set
                password = (select i.password from identities i where i.id = users.identity_id),
                two_factor_secret = (select i.two_factor_secret from identities i where i.id = users.identity_id),
                two_factor_confirmed_at = (select i.two_factor_confirmed_at from identities i where i.id = users.identity_id),
                two_factor_recovery_codes = (select i.two_factor_recovery_codes from identities i where i.id = users.identity_id)
        SQL);

        // Sem senha a conta não entra; o rollback não pode deixar a coluna nullable.
        DB::table('users')->whereNull('password')->update(['password' => '']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });

        // Volta o unique global do e-mail. Contas irmãs criadas depois da ida impedem o rollback:
        // é a mesma pessoa com o mesmo e-mail em dois papéis, e só a equipe decide qual fica.
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_index');
            $table->unique('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['identity_id', 'user_type']);
            $table->dropForeign(['identity_id']);
            $table->dropColumn('identity_id');
        });

        Schema::dropIfExists('identities');
    }
};

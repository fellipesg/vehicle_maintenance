<?php

use App\Models\Garage;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CNPJ da loja em garages.document (texto, porque o cast é encrypted e o valor guardado é o
 * criptografado). Até aqui o CNPJ do lojista só existia em users.document, preenchido apenas no
 * cadastro público — que recusa conta de garagem —, então na prática nenhuma loja conseguia tê-lo.
 *
 * O up() leva para a loja o documento que porventura esteja na conta e cria a linha de garages que
 * falte: contas viradas para 'garage' depois do cadastro não passam por TenantService e ficaram sem
 * ela. users.document continua como está (User::normalizedDocument ainda cai nele).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->text('document')->nullable()->after('name');
        });

        User::query()
            ->where('user_type', 'garage')
            ->whereNotNull('tenant_id')
            ->with('garage')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $garage = $user->garage ?? new Garage([
                        'tenant_id' => $user->tenant_id,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'email' => $user->email,
                    ]);

                    if ($garage->exists && filled($garage->document)) {
                        continue;
                    }

                    $garage->document = $user->document;
                    $garage->save();
                }
            });
    }

    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->dropColumn('document');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contestação do proprietário sobre uma OS que uma oficina SEM vínculo provado registrou no carro
 * dele (sem selo, owner_status pending; ver App\Support\Maintenance\WorkshopVehicleLink).
 *
 * É a ação que o dono tem SEM o CRLV-e. Decidir (vincular, recusar, ocultar) exige propriedade
 * verificada, porque recusar apaga anexos e ocultar tira o registro de todos os próximos donos —
 * quem só digitou um chassi não pode fazer isso com histórico de outra pessoa. Contestar não apaga
 * nem esconde nada em silêncio: marca o registro como contestado, tira ele das superfícies públicas
 * enquanto a disputa corre e leva o caso para a equipe. Nada se perde, e a vítima de uma OS fantasma
 * deixa de ficar sem resposta até conseguir o documento.
 *
 * Só vale para a OS sem selo: a selada (histórico de carro sem dono, confirmado por quem prestou o
 * serviço) continua só no caminho do CRLV-e.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->timestamp('owner_disputed_at')->nullable()->after('owner_decided_at');
            $table->foreignId('owner_disputed_by_user_id')->nullable()->after('owner_disputed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('owner_dispute_reason', 500)->nullable()->after('owner_disputed_by_user_id')
                ->comment('O que o proprietário alega, no texto dele; opcional');

            $table->index('owner_disputed_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropIndex(['owner_disputed_at']);
            $table->dropConstrainedForeignId('owner_disputed_by_user_id');
            $table->dropColumn(['owner_disputed_at', 'owner_dispute_reason']);
        });
    }
};

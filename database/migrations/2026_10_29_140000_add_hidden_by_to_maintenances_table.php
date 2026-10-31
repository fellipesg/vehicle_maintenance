<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem da equipe tirou o registro da consulta pública, e por quê.
 *
 * Ocultar deixou de ser decisão do proprietário e passou a ser da equipe
 * (App\Services\Maintenance\MaintenancePublicVisibilityService). Suprimir um registro é tirar de
 * circulação o ativo do produto, então não pode ser anônimo: antes o rastro ia para
 * owner_decided_by_user_id, que é do dono e serve para ele continuar vendo o que ocultou — misturar
 * os dois deixaria o admin se passando por proprietário na própria regra de visibilidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->foreignId('hidden_by_user_id')->nullable()->after('hidden_from_public_at')
                ->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 500)->nullable()->after('hidden_by_user_id')
                ->comment('Por que a equipe ocultou: fraude confirmada, erro de chassi, pedido honrado');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by_user_id');
            $table->dropColumn('hidden_reason');
        });
    }
};

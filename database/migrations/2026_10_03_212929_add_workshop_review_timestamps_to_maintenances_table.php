<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quando o pedido de validação foi feito (base do lembrete de 7 dias) e quando a oficina foi
     * lembrada dele (cada pedido gera no máximo um lembrete).
     */
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->timestamp('workshop_review_requested_at')->nullable()->after('workshop_review_status');
            $table->timestamp('workshop_review_reminded_at')->nullable()->after('workshop_review_requested_at');
        });

        DB::table('maintenances')
            ->where('workshop_review_status', 'pending')
            ->update(['workshop_review_requested_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn(['workshop_review_requested_at', 'workshop_review_reminded_at']);
        });
    }
};

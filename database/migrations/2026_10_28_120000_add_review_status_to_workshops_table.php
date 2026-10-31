<?php

use App\Enums\WorkshopStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Situação do cadastro da oficina, no mesmo formato de garages (status +
 * reviewed_by/reviewed_at/review_notes).
 *
 * A coluna nasce 'pending' porque toda oficina nova precisa passar pela análise, mas as que já
 * existem são marcadas 'approved' aqui: sem isso, toda oficina em produção ficaria trancada na
 * virada — e junto com ela o Selo das OS que ela ainda vai registrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->string('status', 20)->default(WorkshopStatus::Pending->value)->after('cnpj');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });

        DB::table('workshops')->update(['status' => WorkshopStatus::Approved->value]);
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'reviewed_at', 'review_notes']);
        });
    }
};

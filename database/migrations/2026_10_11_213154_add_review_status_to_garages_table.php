<?php

use App\Enums\GarageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Situação do cadastro da loja, no mesmo formato que vehicle_consignments já usa para a procuração
 * (status + reviewed_by/reviewed_at/review_notes).
 *
 * A coluna nasce 'pending' porque toda loja nova precisa passar pela análise, mas as que já existem
 * são marcadas 'approved' aqui: sem isso, toda garagem em produção ficaria trancada na virada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->string('status', 20)->default(GarageStatus::Pending->value)->after('document');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });

        DB::table('garages')->update(['status' => GarageStatus::Approved->value]);
    }

    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'reviewed_at', 'review_notes']);
        });
    }
};

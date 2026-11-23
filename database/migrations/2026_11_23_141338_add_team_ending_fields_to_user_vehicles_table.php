<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem da equipe encerrou o vínculo de propriedade, e por quê
 * (App\Services\Vehicle\VehicleOwnershipEndingService). Nulos em todo vínculo encerrado de outro
 * jeito (CRLV-e de outra conta, venda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_vehicles', function (Blueprint $table) {
            $table->foreignId('ownership_ended_by_user_id')->nullable()->after('is_current_owner')->constrained('users')->nullOnDelete();
            $table->text('ownership_ended_reason')->nullable()->after('ownership_ended_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ownership_ended_by_user_id');
            $table->dropColumn('ownership_ended_reason');
        });
    }
};

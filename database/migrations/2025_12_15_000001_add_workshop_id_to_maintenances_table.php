<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->foreignId('workshop_id')->nullable()->after('user_id')->constrained()->onDelete('set null');
            // Manter workshop_name por compatibilidade, mas será preenchido automaticamente se workshop_id existir
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropForeign(['workshop_id']);
            $table->dropColumn('workshop_id');
        });
    }
};

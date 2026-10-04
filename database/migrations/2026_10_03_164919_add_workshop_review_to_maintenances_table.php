<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Validação pela oficina de uma manutenção declarada que a cita (workshop_id sem selo):
     * pending → confirmed (recebe o Selo da oficina) ou rejected (o vínculo sai, o nome fica).
     * verification_method diz como o selo nasceu: registered (a oficina registrou a OS) ou
     * confirmed (a oficina confirmou o que o cliente declarou).
     */
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('workshop_review_status', 20)->nullable()->after('verification_code');
            $table->timestamp('workshop_reviewed_at')->nullable()->after('workshop_review_status');
            $table->foreignId('workshop_reviewed_by')->nullable()->after('workshop_reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('workshop_review_note', 500)->nullable()->after('workshop_reviewed_by');
            $table->foreignId('rejected_workshop_id')->nullable()->after('workshop_review_note')->constrained('workshops')->nullOnDelete();
            $table->string('verification_method', 20)->nullable()->after('rejected_workshop_id');
            $table->foreignId('workshop_lead_id')->nullable()->after('verification_method')->constrained('workshop_leads')->nullOnDelete();

            $table->index(['workshop_id', 'workshop_review_status']);
        });

        DB::table('maintenances')
            ->whereNotNull('workshop_id')
            ->whereNull('verified_at')
            ->update(['workshop_review_status' => 'pending']);

        DB::table('maintenances')
            ->whereNotNull('verified_at')
            ->update(['verification_method' => 'registered']);
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropIndex(['workshop_id', 'workshop_review_status']);
            $table->dropConstrainedForeignId('workshop_lead_id');
            $table->dropConstrainedForeignId('rejected_workshop_id');
            $table->dropConstrainedForeignId('workshop_reviewed_by');
            $table->dropColumn(['workshop_review_status', 'workshop_reviewed_at', 'workshop_review_note', 'verification_method']);
        });
    }
};

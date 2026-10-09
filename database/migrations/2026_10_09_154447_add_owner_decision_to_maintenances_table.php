<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentimento do proprietário sobre a OS que a oficina registrou num carro sem dono.
 * owner_status nulo = OS comum (a OS nasceu com dono). attachments_status descreve as notas e fotos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('owner_status', 20)->nullable()->after('tenant_id')
                ->comment('pending|linked|declined; nulo quando a OS nasceu com proprietário');
            $table->string('attachments_status', 20)->default('none')->after('owner_status')
                ->comment('none|pending|accepted|declined|revoked');
            $table->timestamp('hidden_from_public_at')->nullable()->after('attachments_status');
            $table->foreignId('owner_decided_by_user_id')->nullable()->after('hidden_from_public_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('owner_decided_at')->nullable()->after('owner_decided_by_user_id');

            $table->index(['owner_status', 'attachments_status'], 'maintenances_owner_attachments_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropIndex('maintenances_owner_attachments_status_index');
            $table->dropConstrainedForeignId('owner_decided_by_user_id');
            $table->dropColumn(['owner_status', 'attachments_status', 'hidden_from_public_at', 'owner_decided_at']);
        });
    }
};

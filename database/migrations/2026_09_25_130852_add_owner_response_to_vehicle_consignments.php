<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_consignments', function (Blueprint $table) {
            // The token authenticates the owner for every action in the e-mail, not only disputes.
            $table->renameColumn('owner_dispute_token', 'owner_action_token');
        });

        Schema::table('vehicle_consignments', function (Blueprint $table) {
            $table->timestamp('history_requested_at')->nullable()->after('history_access_status')
                ->comment('Quando a garagem pediu acesso ao histórico anterior');
            $table->string('history_approved_via', 20)->nullable()->after('history_requested_at')
                ->comment('owner ou staff');
            $table->timestamp('owner_disputed_at')->nullable()->after('owner_notified_at');
            $table->text('owner_dispute_note')->nullable()->after('owner_disputed_at');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_consignments', function (Blueprint $table) {
            $table->dropColumn([
                'history_requested_at',
                'history_approved_via',
                'owner_disputed_at',
                'owner_dispute_note',
            ]);
        });

        Schema::table('vehicle_consignments', function (Blueprint $table) {
            $table->renameColumn('owner_action_token', 'owner_dispute_token');
        });
    }
};

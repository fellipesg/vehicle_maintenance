<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_message_dispatches', function (Blueprint $table) {
            $table->timestamp('clicked_at')->nullable()->after('sent_at');
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('workshop_message_dispatches', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'sent_at']);
            $table->dropColumn('clicked_at');
        });
    }
};

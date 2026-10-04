<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_prospects', function (Blueprint $table) {
            $table->string('first_message_id')->nullable()->after('first_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_prospects', function (Blueprint $table) {
            $table->dropColumn('first_message_id');
        });
    }
};

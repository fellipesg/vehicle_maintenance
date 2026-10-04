<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->string('plan', 20)->default('free')->after('logo_path');
            $table->boolean('weekly_digest_enabled')->default(true)->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->dropColumn(['plan', 'weekly_digest_enabled']);
        });
    }
};

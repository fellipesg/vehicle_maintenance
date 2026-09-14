<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->boolean('has_warranty')->default(false)->after('part_number');
            $table->date('warranty_starts_at')->nullable()->after('has_warranty');
            $table->date('warranty_ends_at')->nullable()->after('warranty_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->dropColumn(['has_warranty', 'warranty_starts_at', 'warranty_ends_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_photos', function (Blueprint $table) {
            $table->string('thumb_path')->nullable()->after('path');
            $table->string('display_path')->nullable()->after('thumb_path');
            $table->unsignedInteger('width')->nullable()->after('display_path');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->unsignedInteger('bytes')->nullable()->after('height');
            $table->string('dominant_color', 7)->nullable()->after('bytes');
            $table->timestamp('processed_at')->nullable()->after('dominant_color');

            $table->index(['maintenance_id', 'stage', 'subject', 'sort'], 'maintenance_photos_group_sort_index');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_photos', function (Blueprint $table) {
            $table->dropIndex('maintenance_photos_group_sort_index');
            $table->dropIndex(['processed_at']);
            $table->dropColumn([
                'thumb_path',
                'display_path',
                'width',
                'height',
                'bytes',
                'dominant_color',
                'processed_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 20);
            $table->string('stage', 20);
            $table->string('path');
            $table->string('original_name');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['maintenance_id', 'subject', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_photos');
    }
};

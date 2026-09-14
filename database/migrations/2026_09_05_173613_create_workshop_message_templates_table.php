<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('trigger');
            $table->enum('service_category', [
                'mechanical',
                'electrical',
                'suspension',
                'painting',
                'finishing',
                'interior',
                'other',
            ])->nullable();
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('lead_kilometers')->default(2000);
            $table->unsignedInteger('min_days_since_service')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['workshop_id', 'trigger', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_message_templates');
    }
};

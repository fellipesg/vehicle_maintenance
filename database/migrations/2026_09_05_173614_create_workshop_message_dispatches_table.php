<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_message_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('workshop_message_templates')->cascadeOnDelete();
            $table->string('dedupe_key');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['template_id', 'user_id', 'vehicle_id', 'dedupe_key'], 'workshop_message_dispatches_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_message_dispatches');
    }
};

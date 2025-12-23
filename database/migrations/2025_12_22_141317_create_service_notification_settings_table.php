<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->boolean('notify_6_months_before')->default(false);
            $table->boolean('notify_3_months_before')->default(false);
            $table->boolean('notify_1_month_before')->default(true);
            $table->boolean('notify_15_days_before')->default(true);
            $table->boolean('notify_1_day_before')->default(true);
            $table->boolean('notify_expired')->default(true);
            $table->timestamps();
            
            $table->unique('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_notification_settings');
    }
};

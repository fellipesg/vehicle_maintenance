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
        Schema::create('user_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->date('purchase_date')->nullable()->comment('Data de compra do veículo');
            $table->date('sale_date')->nullable()->comment('Data de venda do veículo');
            $table->boolean('is_current_owner')->default(true)->comment('Se é o proprietário atual');
            $table->timestamps();
            
            $table->unique(['user_id', 'vehicle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_vehicles');
    }
};

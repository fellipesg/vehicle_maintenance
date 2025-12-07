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
        Schema::create('maintenance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->onDelete('cascade');
            $table->string('name', 255)->comment('Nome do item/peça');
            $table->text('description')->nullable()->comment('Descrição do item');
            $table->integer('quantity')->default(1)->comment('Quantidade');
            $table->decimal('unit_price', 10, 2)->nullable()->comment('Preço unitário');
            $table->decimal('total_price', 10, 2)->nullable()->comment('Preço total');
            $table->string('part_number', 100)->nullable()->comment('Número da peça');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_items');
    }
};

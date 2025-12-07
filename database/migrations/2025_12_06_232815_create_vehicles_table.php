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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate', 10)->unique()->comment('Placa do veículo');
            $table->string('renavam', 20)->unique()->comment('RENAVAM do veículo');
            $table->string('brand', 100)->comment('Marca');
            $table->string('model', 100)->comment('Modelo');
            $table->year('year')->comment('Ano');
            $table->string('color', 50)->nullable()->comment('Cor');
            $table->string('chassis', 50)->nullable()->comment('Chassi');
            $table->string('engine', 50)->nullable()->comment('Motor');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

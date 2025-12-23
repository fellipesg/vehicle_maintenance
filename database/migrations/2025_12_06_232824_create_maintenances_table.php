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
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('Usuário que registrou a manutenção');
            $table->string('maintenance_type', 100)->comment('Tipo de revisão/manutenção');
            $table->text('description')->nullable()->comment('Descrição dos serviços executados');
            $table->string('workshop_name', 255)->nullable()->comment('Nome da oficina');
            $table->date('maintenance_date')->comment('Data da finalização da revisão');
            $table->integer('kilometers')->nullable()->comment('Quilometragem do veículo');
            $table->enum('service_category', [
                'mechanical',
                'electrical',
                'suspension',
                'painting',
                'finishing',
                'interior',
                'other'
            ])->default('other')->comment('Categoria do serviço');
            $table->boolean('is_manufacturer_required')->default(false)->comment('Se é manutenção requerida pelo manual');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};

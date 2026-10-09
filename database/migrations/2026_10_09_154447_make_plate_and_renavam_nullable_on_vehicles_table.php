<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A oficina cria o veículo só pelo chassi (marca, modelo e ano), sem placa nem RENAVAM: dados
 * pessoais que só o proprietário traz. Os dois seguem únicos (vários NULL convivem no índice) e
 * veículo sem o dado guarda NULL, nunca texto vazio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('license_plate', 10)->nullable()->comment('Placa do veículo')->change();
            $table->string('renavam', 20)->nullable()->comment('RENAVAM do veículo')->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('license_plate', 10)->nullable(false)->comment('Placa do veículo')->change();
            $table->string('renavam', 20)->nullable(false)->comment('RENAVAM do veículo')->change();
        });
    }
};

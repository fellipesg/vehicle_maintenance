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
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nome da oficina');
            $table->string('phone')->comment('Telefone');
            $table->string('whatsapp')->nullable()->comment('WhatsApp (pode ser o mesmo telefone)');
            $table->string('email')->nullable()->comment('Email opcional');
            $table->string('facebook')->nullable()->comment('Perfil do Facebook');
            $table->string('instagram')->nullable()->comment('Perfil do Instagram');
            $table->string('cep', 8)->comment('CEP');
            $table->string('street')->comment('Logradouro');
            $table->string('number')->comment('Número');
            $table->string('complement')->nullable()->comment('Complemento');
            $table->string('neighborhood')->comment('Bairro');
            $table->string('city')->comment('Cidade');
            $table->string('state', 2)->comment('Estado (UF)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshops');
    }
};

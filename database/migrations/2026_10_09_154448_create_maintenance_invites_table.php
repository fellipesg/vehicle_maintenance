<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convite que a oficina faz ao cliente de uma OS sem proprietário. Guarda só o necessário: o token
 * da página /convite/{token}, o hash do e-mail (nunca o endereço) e as datas. O telefone do
 * WhatsApp não é guardado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('email_hash', 64)->nullable();
            $table->timestamp('email_invited_at')->nullable();
            $table->timestamp('whatsapp_invited_at')->nullable();
            $table->timestamps();

            $table->index(['workshop_id', 'email_invited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_invites');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quem não quer mais mensagens de oficinas: workshop_id preenchido bloqueia uma oficina,
     * workshop_id nulo bloqueia todas.
     */
    public function up(): void
    {
        Schema::create('workshop_message_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workshop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'workshop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_message_opt_outs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comunicados únicos já enviados a cada usuário (ex.: lançamento do app iOS), para nunca repetir.
     */
    public function up(): void
    {
        Schema::create('user_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('announcement', 60);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['user_id', 'announcement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_announcements');
    }
};

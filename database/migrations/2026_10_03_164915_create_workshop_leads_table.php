<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oficinas citadas pelo nome em manutenções declaradas, mas que ainda não têm conta: a fila de
     * convites do admin. Uma linha por nome normalizado + cidade.
     */
    public function up(): void
    {
        Schema::create('workshop_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->unsignedInteger('mentions_count')->default(0);
            $table->timestamp('last_mentioned_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['normalized_name', 'city']);
            $table->index('mentions_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_leads');
    }
};

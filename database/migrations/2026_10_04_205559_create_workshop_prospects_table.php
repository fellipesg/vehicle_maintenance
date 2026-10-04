<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_prospects', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj', 14)->unique();
            $table->string('trade_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('cnae', 7);
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('city');
            $table->string('state', 2);
            $table->string('source')->default('receita_cnpj');
            $table->string('token', 40)->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('first_sent_at')->nullable();
            $table->timestamp('follow_up_sent_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_prospects');
    }
};

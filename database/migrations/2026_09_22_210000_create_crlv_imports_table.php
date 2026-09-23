<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crlv_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->index();
            $table->string('mode', 20)->default('import');
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            // Traz nome e CPF/CNPJ do proprietário: guardado cifrado e podado
            // assim que expira.
            $table->text('parsed');
            $table->text('pending_vehicle_data')->nullable();
            $table->string('source_filename')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crlv_imports');
    }
};

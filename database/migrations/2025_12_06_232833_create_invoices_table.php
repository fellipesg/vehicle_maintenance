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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->onDelete('cascade');
            $table->foreignId('maintenance_item_id')->nullable()->constrained()->onDelete('cascade')->comment('Null se for nota fiscal geral da revisão');
            $table->enum('invoice_type', ['item', 'general'])->default('general')->comment('Tipo: item específico ou nota geral da revisão');
            $table->string('file_path', 500)->comment('Caminho do arquivo PDF da nota fiscal');
            $table->string('file_name', 255)->comment('Nome original do arquivo');
            $table->string('invoice_number', 100)->nullable()->comment('Número da nota fiscal');
            $table->date('invoice_date')->nullable()->comment('Data da nota fiscal');
            $table->decimal('total_amount', 10, 2)->nullable()->comment('Valor total da nota');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->string('cnpj', 14)->nullable()->unique()->after('name')->comment('CNPJ só com dígitos; as oficinas antigas não têm');
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->dropUnique(['cnpj']);
            $table->dropColumn('cnpj');
        });
    }
};

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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('postal_code', 10)->nullable()->after('phone')->comment('CEP');
            $table->string('street', 255)->nullable()->after('postal_code')->comment('Logradouro');
            $table->string('number', 20)->nullable()->after('street')->comment('Número');
            $table->string('complement', 255)->nullable()->after('number')->comment('Complemento');
            $table->string('city', 100)->nullable()->after('complement')->comment('Cidade');
            $table->string('state', 2)->nullable()->after('city')->comment('Estado (UF)');
            $table->string('country', 100)->default('Brasil')->after('state')->comment('País');
            $table->string('provider')->nullable()->after('country')->comment('SSO Provider (google, twitter, facebook)');
            $table->string('provider_id')->nullable()->after('provider')->comment('ID do provider SSO');
            $table->string('avatar')->nullable()->after('provider_id')->comment('URL do avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'postal_code',
                'street',
                'number',
                'complement',
                'city',
                'state',
                'country',
                'provider',
                'provider_id',
                'avatar',
            ]);
        });
    }
};

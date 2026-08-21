<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedInteger('current_kilometers')->nullable()->after('cover_photo_path')
                ->comment('Quilometragem atual conhecida do veículo');
            $table->unsignedInteger('odometer_at_registration')->nullable()->after('current_kilometers')
                ->comment('Quilometragem informada no cadastro');
        });

        Schema::table('user_vehicles', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('ownership_type');
            $table->string('terms_version', 32)->nullable()->after('terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['current_kilometers', 'odometer_at_registration']);
        });

        Schema::table('user_vehicles', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version']);
        });
    }
};

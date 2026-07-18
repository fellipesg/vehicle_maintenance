<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('crv_number', 20)->nullable()->after('renavam');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('document', 20)->nullable()->after('phone')->comment('CPF ou CNPJ');
            $table->boolean('subscription_active')->default(false)->after('is_admin');
        });

        Schema::table('user_vehicles', function (Blueprint $table) {
            $table->timestamp('ownership_verified_at')->nullable()->after('is_current_owner');
            $table->unsignedSmallInteger('crlv_exercise_year')->nullable()->after('ownership_verified_at');
            $table->string('owner_document', 20)->nullable()->after('crlv_exercise_year');
            $table->string('ownership_type', 20)->default('owner')->after('owner_document');
        });

        Schema::create('vehicle_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('grant_type', 30)->default('consignment');
            $table->string('status', 20)->default('pending');
            $table->string('power_of_attorney_path')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'vehicle_id', 'grant_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_access_grants');

        Schema::table('user_vehicles', function (Blueprint $table) {
            $table->dropColumn(['ownership_verified_at', 'crlv_exercise_year', 'owner_document', 'ownership_type']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['document', 'subscription_active']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('crv_number');
        });
    }
};

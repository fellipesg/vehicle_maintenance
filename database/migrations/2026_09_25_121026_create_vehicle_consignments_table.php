<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_consignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garage_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Proprietário quando ele já tem conta na plataforma');

            $table->string('owner_name');
            $table->string('owner_email')->nullable();
            $table->string('owner_phone', 30)->nullable();
            $table->string('owner_document', 20)->nullable()->comment('CPF/CNPJ do CRLV-e');

            $table->timestamp('declaration_accepted_at');
            $table->string('declaration_ip', 45)->nullable();
            $table->string('declaration_user_agent')->nullable();

            $table->string('power_of_attorney_path')->nullable();
            $table->string('history_access_status', 20)->default('none')
                ->comment('none, pending, approved, rejected');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->string('status', 20)->default('active')->comment('active, ended');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 30)->nullable()->comment('sold, owner_withdrew, revoked');

            $table->timestamp('owner_notified_at')->nullable();
            $table->string('owner_dispute_token', 64)->nullable()->unique();

            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index(['garage_user_id', 'status']);
        });

        $this->backfillFromAccessGrants();
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_consignments');
    }

    /**
     * Consignments used to live as pending power-of-attorney grants that nobody could approve.
     * Carry them over so those garages keep the vehicle in their stock.
     */
    private function backfillFromAccessGrants(): void
    {
        if (! Schema::hasTable('vehicle_access_grants')) {
            return;
        }

        $grants = DB::table('vehicle_access_grants')
            ->where('grant_type', 'consignment')
            ->get();

        foreach ($grants as $grant) {
            $pivot = DB::table('user_vehicles')
                ->where('user_id', $grant->user_id)
                ->where('vehicle_id', $grant->vehicle_id)
                ->first();

            $user = DB::table('users')->where('id', $grant->user_id)->first();

            DB::table('vehicle_consignments')->insert([
                'vehicle_id' => $grant->vehicle_id,
                'garage_user_id' => $grant->user_id,
                'tenant_id' => $pivot->tenant_id ?? $user->tenant_id ?? null,
                'owner_user_id' => null,
                'owner_name' => 'Proprietário não informado',
                'owner_email' => null,
                'owner_phone' => null,
                'owner_document' => $pivot->owner_document ?? null,
                'declaration_accepted_at' => $grant->created_at,
                'declaration_ip' => null,
                'declaration_user_agent' => null,
                'power_of_attorney_path' => $grant->power_of_attorney_path,
                'history_access_status' => match ($grant->status) {
                    'approved' => 'approved',
                    'rejected' => 'rejected',
                    default => 'pending',
                },
                'reviewed_by' => $grant->reviewed_by,
                'reviewed_at' => $grant->reviewed_at,
                'review_notes' => $grant->review_notes,
                'status' => 'active',
                'started_at' => $grant->created_at,
                'ended_at' => null,
                'end_reason' => null,
                'owner_notified_at' => null,
                'owner_dispute_token' => null,
                'created_at' => $grant->created_at,
                'updated_at' => $grant->updated_at,
            ]);
        }
    }
};

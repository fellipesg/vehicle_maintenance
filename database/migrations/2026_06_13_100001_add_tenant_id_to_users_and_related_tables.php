<?php

use App\Models\Maintenance;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('user_type')->constrained()->nullOnDelete();
        });

        Schema::table('user_vehicles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('vehicle_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('maintenances', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('workshops', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        $this->backfillTenants();
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('user_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }

    private function backfillTenants(): void
    {
        User::query()->whereNull('tenant_id')->each(function (User $user) {
            $type = match ($user->user_type) {
                'garage' => 'garage',
                'workshop' => 'workshop',
                default => 'individual',
            };

            $tenant = Tenant::create([
                'type' => $type,
                'name' => $user->name,
            ]);

            $user->update(['tenant_id' => $tenant->id]);

            DB::table('user_vehicles')
                ->where('user_id', $user->id)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $tenant->id]);

            Maintenance::query()
                ->where('user_id', $user->id)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $tenant->id]);

            $workshop = Workshop::query()->where('user_id', $user->id)->first();
            if ($workshop) {
                $workshop->update(['tenant_id' => $tenant->id]);
            }
        });
    }
};

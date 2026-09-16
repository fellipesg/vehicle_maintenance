<?php

use App\Models\Vehicle;
use App\Support\VehiclePlateSearch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeExistingChassis();
        $this->assertNoDuplicateChassis();

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('chassis');
        });

        Schema::create('vehicle_plates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('plate', 10);
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->string('source', 30);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('plate');
            $table->unique(['vehicle_id', 'plate', 'started_at']);
        });

        $this->backfillVehiclePlates();
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_plates');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['chassis']);
        });
    }

    private function normalizeExistingChassis(): void
    {
        Vehicle::query()
            ->whereNotNull('chassis')
            ->where('chassis', '!=', '')
            ->orderBy('id')
            ->each(function (Vehicle $vehicle): void {
                $normalized = Vehicle::normalizeChassis((string) $vehicle->chassis);
                if ($normalized === '') {
                    $vehicle->forceFill(['chassis' => null])->saveQuietly();

                    return;
                }

                if ($vehicle->chassis !== $normalized) {
                    $vehicle->forceFill(['chassis' => $normalized])->saveQuietly();
                }
            });
    }

    private function assertNoDuplicateChassis(): void
    {
        /** @var array<string, list<int>> $byChassis */
        $byChassis = [];

        Vehicle::query()
            ->whereNotNull('chassis')
            ->where('chassis', '!=', '')
            ->orderBy('id')
            ->each(function (Vehicle $vehicle) use (&$byChassis): void {
                $byChassis[(string) $vehicle->chassis][] = (int) $vehicle->id;
            });

        $lines = [];
        foreach ($byChassis as $chassis => $ids) {
            if (count($ids) > 1) {
                $lines[] = "chassi {$chassis}: veículos ".implode(',', $ids);
            }
        }

        if ($lines !== []) {
            throw new \RuntimeException(
                'Não foi possível criar índice único em vehicles.chassis — chassi duplicado. Resolva manualmente: '
                .implode('; ', $lines)
            );
        }
    }

    private function backfillVehiclePlates(): void
    {
        $now = now();

        Vehicle::query()
            ->whereNotNull('license_plate')
            ->where('license_plate', '!=', '')
            ->orderBy('id')
            ->each(function (Vehicle $vehicle) use ($now): void {
                DB::table('vehicle_plates')->insert([
                    'vehicle_id' => $vehicle->id,
                    'plate' => VehiclePlateSearch::normalize((string) $vehicle->license_plate),
                    'started_at' => null,
                    'ended_at' => null,
                    'source' => 'backfill',
                    'changed_by_user_id' => null,
                    'tenant_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
};

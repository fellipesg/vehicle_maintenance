<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('warranty_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope');
            $table->string('name');
            $table->text('body');
            $table->unsignedInteger('duration_days');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->timestamps();

            $table->unique('maintenance_item_id');
            $table->index(['maintenance_id', 'scope']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX mw_one_order_warranty ON maintenance_warranties (maintenance_id) WHERE scope = 'order'");
        } elseif ($driver === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX mw_one_order_warranty ON maintenance_warranties (maintenance_id) WHERE scope = 'order'");
        } elseif ($driver === 'mysql') {
            DB::statement("CREATE UNIQUE INDEX mw_one_order_warranty ON maintenance_warranties ((CASE WHEN scope = 'order' THEN maintenance_id ELSE NULL END))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_warranties');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O veículo tem **um** odômetro, e até aqui o km dele estava em quatro colunas de três tabelas
 * (vehicles.current_kilometers, vehicles.odometer_at_registration, maintenances.kilometers e
 * vehicle_inspections.odometer_km). Nenhuma delas é "a leitura", e é por isso que o lembrete de
 * revisão quase nunca dispara e que o teto de plausibilidade tinha furo.
 *
 * Esta tabela é a fonte da verdade **do odômetro** — não da OS nem do laudo, que seguem donos dos
 * dados deles. Cada escrita de km deixa aqui uma cópia de mão única: quem escreve é a origem, a
 * leitura acompanha, e ninguém edita a leitura direto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_odometer_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            // Leitura sem valor não é leitura: a coluna não é nulável, ao contrário de
            // maintenances.kilometers (OS pode nascer sem km informado).
            $table->unsignedInteger('kilometers');

            // Quando o odômetro marcava isso — e não quando a linha foi digitada, que é o
            // created_at. A OS é registrada hoje para serviço feito há dois anos, e é neste eixo
            // que a regra de km já compara vizinhos (VehicleMileageService, whereDate).
            // `date` e não datetime porque a OS só conhece o dia; empate no mesmo dia desempata
            // por created_at e depois id (VehicleOdometerReading::scopeLatestFirst).
            $table->date('measured_at');

            $table->string('source', 32);

            // Espelha App\Models\Maintenance::scopeCountsTowardOdometer: fica de fora o que o
            // proprietário contestou e o que a equipe suprimiu. O laudo da cautelar nasce false.
            $table->boolean('counts_toward_odometer')->default(true);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();

            // Auditoria apenas, nunca filtro: o odômetro pertence ao chassi, não à conta — é a
            // mesma razão pela qual a OS de carro sem dono nasce com tenant_id nulo.
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('maintenance_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_inspection_id')->nullable()->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->index(['vehicle_id', 'measured_at']);

            // O que torna projeção duplicada impossível no banco, e não só no código: uma OS (ou um
            // laudo) tem no máximo uma leitura. É também o que deixa o backfill rodar de novo sem
            // medo.
            $table->unique('maintenance_id');
            $table->unique('vehicle_inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_odometer_readings');
    }
};

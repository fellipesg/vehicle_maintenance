<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vistoria cautelar: uma afirmação *sobre* o veículo, irmã de `maintenances`, que são os serviços
 * feitos *no* veículo. Tabela própria porque o laudo tem vocabulário que nenhuma OS tem — veredito,
 * eixos de achado e validade — e porque contá-lo como manutenção inflaria o histórico de serviços
 * que o produto vende (ver .ai/rules/inspections.md).
 *
 * `workshop_id` é `set null` com `workshop_name` desnormalizado ao lado, como em `maintenances`: o
 * laudo pertence ao chassi e continua legível depois de a oficina sair da base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Tenant do dono atual na emissão; nulo em veículo sem dono');
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Quem emitiu o laudo');
            $table->string('workshop_name')->nullable()
                ->comment('Nome do emissor na emissão, para o laudo sobreviver à exclusão da oficina');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Conta que registrou');

            $table->date('inspected_at')->comment('Data da vistoria');
            $table->integer('odometer_km')->nullable()
                ->comment('Hodômetro lido na vistoria; não alimenta vehicles.current_kilometers');
            $table->string('verdict', 30)->comment('approved | approved_with_restrictions | rejected');
            $table->json('findings')->comment('Eixos de achado: {eixo: {status, note}}');
            $table->text('summary')->nullable()->comment('Resumo do vistoriador');
            $table->date('valid_until')->nullable()->comment('Vence sem desaparecer do histórico');

            $table->string('report_path')->nullable()->comment('Laudo em PDF, caminho aleatório');
            $table->string('report_original_name')->nullable();

            $table->string('verification_code', 12)->nullable()->unique()
                ->comment('LDO-XXXX-XX, a página pública do laudo');

            $table->timestamp('owner_disputed_at')->nullable();
            $table->foreignId('owner_disputed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('owner_dispute_reason')->nullable();

            $table->timestamp('hidden_from_public_at')->nullable();
            $table->foreignId('hidden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('hidden_reason')->nullable();

            $table->timestamps();

            $table->index(['vehicle_id', 'inspected_at']);
            $table->index('workshop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_inspections');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('circuit_incidents')) {
            return;
        }

        Schema::create('circuit_incidents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('circuit_unit_id');
            $table->string('chamado_numero', 50)->nullable();
            $table->boolean('massiva_regiao')->default(false);
            $table->string('unidade', 255);
            $table->unsignedTinyInteger('previsao_resolucao_horas')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['opened_at', 'resolved_at']);
            $table->index('unidade');
            $table->foreign('circuit_unit_id')
                ->references('id_circuitos')
                ->on('circuitos_unidades')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuit_incidents');
    }
};

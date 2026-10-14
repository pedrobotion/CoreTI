<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro das pendências administrativas já sinalizadas, para não abrir o
 * mesmo chamado Jira duas vezes e para saber quando a pendência foi resolvida.
 *
 * Uma linha por (equipamento, tipo de pendência). Enquanto a pendência
 * persiste a linha fica 'aberta'; quando o equipamento sai da condição, a
 * verificação marca 'resolvida'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancada_pendencia_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bancada_equipment_id')->constrained('bancada_equipments')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('situacao', 20)->default('aberta'); // aberta | resolvida
            $table->unsignedInteger('dias')->default(0);
            $table->string('jira_issue_key', 40)->nullable();
            $table->string('jira_issue_url', 255)->nullable();
            $table->timestamp('primeiro_detectado_em')->nullable();
            $table->timestamp('ultimo_alerta_em')->nullable();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();

            $table->unique(['bancada_equipment_id', 'tipo']);
            $table->index('situacao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancada_pendencia_alerts');
    }
};

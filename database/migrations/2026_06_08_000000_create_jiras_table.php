<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela jiras (espelho dos chamados do Jira Service Management).
 *
 * A tabela existe em produção desde antes, criada fora do versionamento — sem
 * esta migration o schema não pode ser reconstruído do zero e qualquer teste
 * que toque em chamados falha com "table doesn't exist".
 *
 * Guardada por hasTable: em produção é ignorada e nada é alterado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jiras')) {
            return;
        }

        Schema::create('jiras', function (Blueprint $table): void {
            $table->id();
            $table->string('chave')->unique();
            $table->string('resumo')->nullable();

            // relator_email é quem REGISTRA o chamado (um operador do
            // ServiceDesk), enquanto relator_nome é o solicitante — por isso
            // um mesmo e-mail aparece com centenas de nomes distintos.
            $table->string('relator_nome')->nullable();
            $table->string('relator_email')->nullable();
            $table->string('responsavel_nome')->nullable();

            $table->string('prioridade')->nullable();
            $table->string('status')->nullable();
            $table->string('unidade')->nullable();
            $table->string('departamento')->nullable();
            $table->string('tipo_requisicao')->nullable();
            $table->string('catalogo')->nullable();
            $table->string('squad')->nullable();

            $table->timestamp('data_hora_criacao')->nullable();
            $table->timestamp('data_hora_atualizacao')->nullable();
            $table->timestamp('data_hora_resolucao')->nullable();
            $table->timestamp('data_hora_resolucao_c')->nullable();

            $table->timestamp('meta_tempo_primeira_resposta')->nullable();
            $table->timestamp('tempo_primeira_resposta')->nullable();
            $table->timestamp('tempo_inicio_sla')->nullable();
            $table->timestamp('tempo_fim_sla')->nullable();

            $table->bigInteger('sla_goalDuration')->nullable();
            $table->bigInteger('sla_elapsedTime')->nullable();
            $table->bigInteger('sla_remainingTime')->nullable();

            $table->timestamp('currentstatus_data_hora')->nullable();
            $table->string('currentstatus_status')->nullable();

            $table->timestamp('tempo_primeira_resposta_inicio')->nullable();
            $table->bigInteger('tempo_primeira_resposta_goalDuration')->nullable();
            $table->bigInteger('tempo_primeira_resposta_elapsedTime')->nullable();
            $table->bigInteger('tempo_primeira_resposta_remainingTime')->nullable();

            $table->bigInteger('tempo_sla_final_goalDuration')->nullable();
            $table->bigInteger('tempo_sla_final_elapsedTime')->nullable();
            $table->bigInteger('tempo_sla_final_remainingTime')->nullable();

            $table->integer('votes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jiras');
    }
};

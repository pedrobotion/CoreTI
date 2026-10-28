<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requisição de peças ao Centro de Distribuição (CD).
 *
 * Cada linha é 1 LOTE: o chamado consolidado aberto no Jira (segunda/quarta
 * 08:00) com todas as peças de origem CD pendentes naquele corte. O lote
 * acompanha o ciclo: aberta → resolvida (Isabeli fez a requisição e resolveu o
 * chamado) → coletada (produtos retirados no CD). Os equipamentos do lote
 * apontam para ele por `bancada_equipments.cd_requisicao_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancada_cd_requisicoes', function (Blueprint $table): void {
            $table->id();
            $table->string('jira_issue_key', 50)->nullable()->index();
            $table->string('jira_issue_url', 500)->nullable();
            // aberta | resolvida | coletada
            $table->string('status', 20)->default('aberta')->index();
            $table->timestamp('aberta_em')->nullable();
            $table->timestamp('resolvida_em')->nullable();
            // Dia em que a coleta fica liberada no CD (regra fixa: dia do pedido + 1).
            $table->date('coleta_liberada_em')->nullable();
            $table->timestamp('coletada_em')->nullable();
            $table->unsignedBigInteger('coletada_por')->nullable();
            $table->unsignedInteger('total_itens')->default(0);
            $table->timestamps();
        });

        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->unsignedBigInteger('cd_requisicao_id')->nullable()->after('peca_admin_realizado_em')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->dropColumn('cd_requisicao_id');
        });

        Schema::dropIfExists('bancada_cd_requisicoes');
    }
};

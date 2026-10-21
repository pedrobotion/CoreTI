<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle do chamado de reposição do estoque interno: quando o saldo bate no
 * mínimo, abre-se 1 chamado de reposição (+ cobrança da unidade) no Jira. A
 * data marca que já há um pedido em aberto (evita abrir outro a cada consumo);
 * a entrada de estoque limpa essas marcas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_stock_products', function (Blueprint $table): void {
            $table->timestamp('reposicao_pendente_em')->nullable()->after('quantidade_atual');
            $table->string('reposicao_jira_key', 30)->nullable()->after('reposicao_pendente_em');
            $table->string('reposicao_jira_url', 500)->nullable()->after('reposicao_jira_key');
        });
    }

    public function down(): void
    {
        Schema::table('internal_stock_products', function (Blueprint $table): void {
            $table->dropColumn(['reposicao_pendente_em', 'reposicao_jira_key', 'reposicao_jira_url']);
        });
    }
};

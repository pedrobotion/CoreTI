<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrada fiscal: operação de origem da nota (Transferência de imobilizado /
 * Remessa p/ manutenção) — obrigatória ao dar entrada.
 * Nota de saída: data de emissão da nota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table) {
            $table->string('nota_operacao_entrada', 40)->nullable()->after('nota_observacao_entrada');
            $table->date('data_emissao_saida')->nullable()->after('nota_numero_saida');
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table) {
            $table->dropColumn(['nota_operacao_entrada', 'data_emissao_saida']);
        });
    }
};

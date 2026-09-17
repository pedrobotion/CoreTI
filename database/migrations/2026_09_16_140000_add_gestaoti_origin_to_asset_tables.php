<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procedência dos registros trazidos do gestaoTI.
 *
 * Serve a duas coisas enquanto os dois sistemas convivem:
 *
 * 1. Idempotência — reexecutar a importação atualiza o que já veio em vez de
 *    duplicar. Sem isso, rodar duas vezes criaria 381 linhas repetidas, e a
 *    unicidade de (ddd, numero) transformaria a segunda execução numa parede
 *    de erros em vez de num relatório útil.
 *
 * 2. Rastreio — dá para responder "de onde veio este registro?" e comparar
 *    com a origem enquanto ela continua sendo usada.
 *
 * asset_assignments guarda também a tabela de origem porque vem de duas
 * ('vinculos' e 'vinculo_notebooks'), cujos ids se sobrepõem.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['corp_lines', 'corp_devices', 'equipments'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela): void {
                $table->unsignedBigInteger('origem_gestaoti_id')->nullable()->after('id');
                $table->unique('origem_gestaoti_id', $tabela.'_origem_gestaoti_unique');
            });
        }

        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->string('origem_gestaoti_tabela', 30)->nullable()->after('id');
            $table->unsignedBigInteger('origem_gestaoti_id')->nullable()->after('origem_gestaoti_tabela');
            $table->unique(['origem_gestaoti_tabela', 'origem_gestaoti_id'], 'asset_assignments_origem_gestaoti_unique');
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->dropUnique('asset_assignments_origem_gestaoti_unique');
            $table->dropColumn(['origem_gestaoti_tabela', 'origem_gestaoti_id']);
        });

        foreach (['corp_lines', 'corp_devices', 'equipments'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela): void {
                $table->dropUnique($tabela.'_origem_gestaoti_unique');
                $table->dropColumn('origem_gestaoti_id');
            });
        }
    }
};

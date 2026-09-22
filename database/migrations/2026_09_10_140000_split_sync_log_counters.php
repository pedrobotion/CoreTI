<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separa os desfechos que estavam somados em assets_unchanged.
 *
 * A coluna acumulava três situações distintas: item enviado para a fila de
 * revisão, item sem ativo correspondente, e item casado que não mudou. Com
 * tudo junto, "332 sem alteração" parecia um sync perfeito quando na verdade
 * significava "332 foram para a fila".
 *
 * Era também o que mantinha o vCenter eternamente "Com avisos": o conector
 * manda todos os itens para revisão por desenho, e a regra de aviso comparava
 * o total encontrado com a lista de casados, que nesse caminho fica vazia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cmdb_sync_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('cmdb_sync_logs', 'assets_review')) {
                $table->unsignedInteger('assets_review')->default(0)->after('assets_unchanged');
            }
            if (! Schema::hasColumn('cmdb_sync_logs', 'assets_unmatched')) {
                $table->unsignedInteger('assets_unmatched')->default(0)->after('assets_review');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cmdb_sync_logs', function (Blueprint $table): void {
            foreach (['assets_unmatched', 'assets_review'] as $coluna) {
                if (Schema::hasColumn('cmdb_sync_logs', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};

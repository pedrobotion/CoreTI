<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observação da entrada fiscal.
 *
 * Campo próprio, e não reaproveitamento de 'observacao': aquela coluna é a
 * observação do equipamento (preenchida na entrada da bancada, exibida na
 * listagem e impressa na etiqueta). Gravar a nota fiscal ali sobrescreveria
 * a informação de 643 equipamentos.
 *
 * Segue o padrão das demais colunas da etapa fiscal (nota_*_entrada) e o
 * precedente de observação por etapa (terceiros_observacoes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('bancada_equipments', 'nota_observacao_entrada')) {
                $table->text('nota_observacao_entrada')->nullable()->after('nota_anexo_entrada');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            if (Schema::hasColumn('bancada_equipments', 'nota_observacao_entrada')) {
                $table->dropColumn('nota_observacao_entrada');
            }
        });
    }
};

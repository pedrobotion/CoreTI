<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O campo Escritório nem sempre guarda um centro de custo.
 *
 * A Rodocoop é empresa terceira: o que vai no campo é o CNPJ dela, não um
 * código do cadastro interno. Sem isso, a alternativa seria cadastrar o CNPJ
 * como se fosse centro de custo — e ele apareceria nas telas de rateio, nos
 * relatórios de custo e em todo lugar que lista centros, como se fosse um.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_office_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('ad_office_rules', 'tipo_valor')) {
                $table->string('tipo_valor', 20)->default('centro_custo')->after('centro_custo_code');
            }
            if (! Schema::hasColumn('ad_office_rules', 'valor_livre')) {
                $table->string('valor_livre')->nullable()->after('tipo_valor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_office_rules', function (Blueprint $table): void {
            foreach (['valor_livre', 'tipo_valor'] as $coluna) {
                if (Schema::hasColumn('ad_office_rules', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};

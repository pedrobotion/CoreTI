<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda o código do centro de custo da unidade escolhida na bancada.
 *
 * A unidade passa a vir do mesmo catálogo do ServiceDesk (coreti_cost_centers).
 * A tela mostra só o nome (unidade_setor); o código fica gravado aqui para
 * casar com o resto do sistema, sem precisar ser exibido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table) {
            $table->string('cost_center_code', 20)->nullable()->after('unidade_setor');
            $table->index('cost_center_code');
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table) {
            $table->dropIndex(['cost_center_code']);
            $table->dropColumn('cost_center_code');
        });
    }
};

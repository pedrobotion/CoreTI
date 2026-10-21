<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nobreak tem catálogo de manutenção próprio no Jira (85), que exige Modelo da
 * Bateria e Quantidade de Baterias. Esses dados passam a ser cadastrados no
 * equipamento (só para Nobreak) e reusados ao abrir o chamado e para controlar
 * requisições de bateria depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table) {
            $table->string('nobreak_modelo_bateria', 20)->nullable()->after('tic');
            $table->unsignedSmallInteger('nobreak_qtd_baterias')->nullable()->after('nobreak_modelo_bateria');
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table) {
            $table->dropColumn(['nobreak_modelo_bateria', 'nobreak_qtd_baterias']);
        });
    }
};

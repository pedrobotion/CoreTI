<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circuitos_unidades', function (Blueprint $table): void {
            $table->string('chamado_numero', 50)->nullable()->after('contato');
            $table->boolean('massiva_regiao')->nullable()->after('chamado_numero');
            $table->string('unidade_operacional', 255)->nullable()->after('massiva_regiao');
            $table->unsignedTinyInteger('previsao_resolucao_horas')->nullable()->after('unidade_operacional');
        });
    }

    public function down(): void
    {
        Schema::table('circuitos_unidades', function (Blueprint $table): void {
            $table->dropColumn([
                'chamado_numero',
                'massiva_regiao',
                'unidade_operacional',
                'previsao_resolucao_horas',
            ]);
        });
    }
};


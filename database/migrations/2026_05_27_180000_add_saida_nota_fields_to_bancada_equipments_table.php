<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->string('nota_documento_saida', 255)->nullable()->after('nota_anexo_entrada');
            $table->string('nota_numero_saida', 80)->nullable()->after('nota_documento_saida');
            $table->string('nota_anexo_saida', 255)->nullable()->after('nota_numero_saida');
            $table->timestamp('nota_saida_emitida_em')->nullable()->after('nota_anexo_saida');
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->dropColumn([
                'nota_documento_saida',
                'nota_numero_saida',
                'nota_anexo_saida',
                'nota_saida_emitida_em',
            ]);
        });
    }
};

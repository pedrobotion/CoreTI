<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca quando a conta foi entregue ao servidor do AD para exclusão.
 *
 * Sem isso, duas execuções simultâneas do executor recebem a MESMA lista: a
 * consulta devolve tudo que está aprovado, e nada muda até o resultado voltar.
 * As duas tentam apagar as mesmas contas — a segunda colhe erro de "não
 * existe" e reporta falha para exclusão que na verdade deu certo.
 *
 * Funciona como concessão temporária: a conta sai da fila ao ser entregue e
 * volta sozinha se o resultado não chegar dentro do prazo, cobrindo o caso do
 * executor morrer no meio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_purge_batch_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('ad_purge_batch_items', 'entregue_em')) {
                $table->timestamp('entregue_em')->nullable()->after('status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_purge_batch_items', function (Blueprint $table): void {
            if (Schema::hasColumn('ad_purge_batch_items', 'entregue_em')) {
                $table->dropColumn('entregue_em');
            }
        });
    }
};

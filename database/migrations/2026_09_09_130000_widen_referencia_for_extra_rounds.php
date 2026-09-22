<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite mais de uma rodada de exclusão no mesmo mês.
 *
 * A competência era fixa em AAAA-MM, então um lote decidido travava o mês
 * inteiro: contas não aprovadas só reapareceriam na virada. Isso é ruim
 * justamente quando mais se precisa de outra rodada — depois de um teste, ou
 * quando parte da lista ficou para conferir com o RH.
 *
 * Com o campo mais largo, a competência aceita um sufixo (2026-09-2) e uma
 * rodada extra pode ser enviada sem apagar o histórico da anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ad_purge_batches')) {
            return;
        }

        // change() exigiria doctrine/dbal; o SQL direto preserva o índice único.
        DB::statement('ALTER TABLE ad_purge_batches MODIFY referencia VARCHAR(16) NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('ad_purge_batches')) {
            return;
        }

        // Rodadas extras impediriam o retorno a 7 caracteres.
        $longas = DB::table('ad_purge_batches')->whereRaw('CHAR_LENGTH(referencia) > 7')->count();

        if ($longas > 0) {
            throw new RuntimeException(
                "Existem {$longas} lotes com competência estendida; reverter truncaria a referência."
            );
        }

        DB::statement('ALTER TABLE ad_purge_batches MODIFY referencia VARCHAR(7) NOT NULL');
    }
};

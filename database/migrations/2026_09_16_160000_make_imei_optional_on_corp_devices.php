<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IMEI deixa de ser obrigatório.
 *
 * Aparelhos antigos entraram no parque antes de o IMEI ser anotado, e não há
 * de onde tirá-lo agora. Exigir o campo deixaria esses aparelhos fora do
 * inventário — pior do que registrá-los sem o número.
 *
 * A unicidade continua: no MySQL um índice UNIQUE aceita vários NULL, então
 * quem tiver IMEI continua não podendo repetir, e quem não tiver convive com
 * os demais sem conflito.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sem doctrine/dbal no projeto, alterar coluna existente pede SQL direto.
        DB::statement('ALTER TABLE `corp_devices` MODIFY `imei_1` VARCHAR(20) NULL');
    }

    public function down(): void
    {
        $semImei = (int) DB::table('corp_devices')->whereNull('imei_1')->count();

        if ($semImei > 0) {
            throw new RuntimeException(
                "Há {$semImei} aparelho(s) cadastrado(s) sem IMEI. Preencha ou remova esses registros antes de voltar a coluna para obrigatória."
            );
        }

        DB::statement('ALTER TABLE `corp_devices` MODIFY `imei_1` VARCHAR(20) NOT NULL');
    }
};

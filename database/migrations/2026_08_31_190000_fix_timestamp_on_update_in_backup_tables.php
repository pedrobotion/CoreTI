<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove o ON UPDATE CURRENT_TIMESTAMP implícito.
 *
 * MySQL/MariaDB atribuem automaticamente
 *   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 * à PRIMEIRA coluna TIMESTAMP NOT NULL de uma tabela quando
 * explicit_defaults_for_timestamp está desligado — o padrão do MariaDB.
 *
 * Efeito prático do defeito:
 *   - cmdb_backup_checks.executado_em era reescrito a cada update do check
 *     (o serviço atualiza incident_id e dados do Jira logo após criá-lo),
 *     corrompendo a ordem cronológica usada na média dos últimos tamanhos;
 *   - cmdb_backup_incidents.aberto_em era reescrito a cada nova ocorrência,
 *     zerando a duração do incidente e inviabilizando MTTR.
 *
 * As colunas passam a ser NULL DEFAULT NULL: a aplicação sempre informa o
 * valor, e nenhum comportamento automático do banco interfere.
 */
return new class extends Migration
{
    private const COLUNAS = [
        'cmdb_backup_checks' => 'executado_em',
        'cmdb_backup_incidents' => 'aberto_em',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::COLUNAS as $tabela => $coluna) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, $coluna)) {
                DB::statement("ALTER TABLE `{$tabela}` MODIFY `{$coluna}` TIMESTAMP NULL DEFAULT NULL");
            }
        }
    }

    public function down(): void
    {
        // Sem reversão: restaurar o ON UPDATE reintroduziria o defeito.
    }
};

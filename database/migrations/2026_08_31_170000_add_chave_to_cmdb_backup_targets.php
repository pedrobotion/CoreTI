<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repara a ausência da coluna 'chave' em cmdb_backup_targets.
 *
 * A migration original falhou ao criar o índice único composto (host,
 * database_name, arquivo), que estoura o limite de 3072 bytes do utf8mb4. A
 * tabela ficou criada sem a coluna de unicidade e, na reexecução, o guard
 * hasTable considerou a tabela pronta e pulou a criação — a migration foi
 * marcada como aplicada sobre uma tabela incompleta.
 *
 * Esta migration corrige de forma incremental, sem recriar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cmdb_backup_targets')) {
            return;
        }

        if (! Schema::hasColumn('cmdb_backup_targets', 'chave')) {
            Schema::table('cmdb_backup_targets', function (Blueprint $table): void {
                $table->char('chave', 64)->nullable()->after('id');
            });
        }

        // Preenche o hash das linhas existentes antes de exigir unicidade.
        DB::table('cmdb_backup_targets')
            ->whereNull('chave')
            ->orderBy('id')
            ->chunkById(200, function ($linhas): void {
                foreach ($linhas as $linha) {
                    DB::table('cmdb_backup_targets')
                        ->where('id', $linha->id)
                        ->update(['chave' => hash('sha256', implode('|', [
                            mb_strtolower(trim((string) $linha->host)),
                            mb_strtolower(trim((string) $linha->database_name)),
                            trim((string) $linha->arquivo),
                        ]))]);
                }
            });

        if (! $this->indiceExiste('cmdb_backup_targets_chave_unique')) {
            Schema::table('cmdb_backup_targets', function (Blueprint $table): void {
                $table->unique('chave');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cmdb_backup_targets', 'chave')) {
            Schema::table('cmdb_backup_targets', function (Blueprint $table): void {
                $table->dropUnique('cmdb_backup_targets_chave_unique');
                $table->dropColumn('chave');
            });
        }
    }

    private function indiceExiste(string $nome): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'cmdb_backup_targets')
            ->where('INDEX_NAME', $nome)
            ->exists();
    }
};

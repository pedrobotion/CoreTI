<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cmdb_discovered_assets')) {
            return;
        }

        $badForeignKey = $this->getForeignKeyName('cmdb_discovered_assets', 'connector_id');
        if ($badForeignKey === '1') {
            DB::statement('ALTER TABLE `cmdb_discovered_assets` DROP FOREIGN KEY `1`');
            if ($this->hasIndex('cmdb_discovered_assets', '1')) {
                DB::statement('ALTER TABLE `cmdb_discovered_assets` DROP INDEX `1`');
            }
        }

        $this->ensureForeignKey('cmdb_discovered_assets', 'sync_log_id', 'cmdb_sync_logs');
        $this->ensureForeignKey('cmdb_discovered_assets', 'asset_id', 'cmdb_assets');
        $this->ensureForeignKey('cmdb_discovered_assets', 'reviewed_by', 'users');

        if (! $this->hasForeignKey('cmdb_discovered_assets', 'connector_id')) {
            $this->ensureForeignKey('cmdb_discovered_assets', 'connector_id', 'cmdb_connectors');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cmdb_discovered_assets')) {
            return;
        }

        foreach ([
            'connector_id',
            'sync_log_id',
            'asset_id',
            'reviewed_by',
        ] as $column) {
            $this->dropForeignKeyIfExists('cmdb_discovered_assets', $column);
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    private function getForeignKeyName(string $table, string $column): ?string
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    private function ensureForeignKey(string $table, string $column, string $referencesTable): void
    {
        if ($this->hasForeignKey($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referencesTable, $table): void {
            $blueprint->foreign($column, $table . '_' . $column . '_foreign')
                ->references('id')
                ->on($referencesTable)
                ->nullOnDelete();
        });
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $foreignKey = $this->getForeignKeyName($table, $column);

        if (! $foreignKey) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
            $table,
            $foreignKey
        ));
    }
};

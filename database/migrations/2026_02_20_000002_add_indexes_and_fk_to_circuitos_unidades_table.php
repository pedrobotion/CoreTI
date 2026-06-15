<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->indexExists('circuitos_unidades', 'circuitos_unidades_operadora_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->index('operadora', 'circuitos_unidades_operadora_index');
            });
        }

        if (! $this->indexExists('circuitos_unidades', 'circuitos_unidades_uf_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->index('uf', 'circuitos_unidades_uf_index');
            });
        }

        if (! $this->indexExists('circuitos_unidades', 'circuitos_unidades_servico_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->index('servico', 'circuitos_unidades_servico_index');
            });
        }

        if (! $this->indexExists('circuitos_unidades', 'circuitos_unidades_unidades_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->index('unidades_circuitos', 'circuitos_unidades_unidades_index');
            });
        }

        if (! $this->indexExists('circuitos_unidades', 'circuitos_unidades_contato_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->index('contato', 'circuitos_unidades_contato_index');
            });
        }

        if (Schema::getConnection()->getDriverName() !== 'sqlite'
            && ! $this->foreignKeyExists('circuitos_unidades', 'circuitos_unidades_id_unidades_fk')
            && $this->canCreateForeignKey('circuitos_unidades', 'id_unidades', 'unidades', 'id_unidades')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->foreign('id_unidades', 'circuitos_unidades_id_unidades_fk')
                    ->references('id_unidades')
                    ->on('unidades')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite' && $this->foreignKeyExists('circuitos_unidades', 'circuitos_unidades_id_unidades_fk')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->dropForeign('circuitos_unidades_id_unidades_fk');
            });
        }

        if ($this->indexExists('circuitos_unidades', 'circuitos_unidades_operadora_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->dropIndex('circuitos_unidades_operadora_index');
            });
        }

        if ($this->indexExists('circuitos_unidades', 'circuitos_unidades_uf_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->dropIndex('circuitos_unidades_uf_index');
            });
        }

        if ($this->indexExists('circuitos_unidades', 'circuitos_unidades_servico_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->dropIndex('circuitos_unidades_servico_index');
            });
        }

        if ($this->indexExists('circuitos_unidades', 'circuitos_unidades_unidades_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->dropIndex('circuitos_unidades_unidades_index');
            });
        }

        if ($this->indexExists('circuitos_unidades', 'circuitos_unidades_contato_index')) {
            Schema::table('circuitos_unidades', function (Blueprint $table) {
                $table->dropIndex('circuitos_unidades_contato_index');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        $database = DB::getDatabaseName();
        if ($database === null) {
            return false;
        }

        $result = DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return $result !== null;
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        $database = DB::getDatabaseName();
        if ($database === null) {
            return false;
        }

        $result = DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ? LIMIT 1',
            [$database, $table, $foreignKeyName, 'FOREIGN KEY']
        );

        return $result !== null;
    }

    private function canCreateForeignKey(string $table, string $column, string $referencedTable, string $referencedColumn): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        $database = DB::getDatabaseName();
        if ($database === null) {
            return false;
        }

        $columnDefinition = DB::selectOne(
            'SELECT column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, $table, $column]
        );

        $referencedDefinition = DB::selectOne(
            'SELECT column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, $referencedTable, $referencedColumn]
        );

        if (! $columnDefinition || ! $referencedDefinition) {
            return false;
        }

        return trim((string) $columnDefinition->column_type) === trim((string) $referencedDefinition->column_type);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('circuitos_unidades as cu')
            ->join('unidades as u', 'u.id_unidades', '=', 'cu.id_unidades')
            ->update([
                'cu.unidades_circuitos' => DB::raw('u.unidade'),
            ]);

        DB::table('circuitos_unidades as cu')
            ->join('unidades as u', 'u.unidade', '=', 'cu.unidades_circuitos')
            ->whereNull('cu.id_unidades')
            ->update([
                'cu.id_unidades' => DB::raw('u.id_unidades'),
            ]);

        DB::table('circuitos_unidades as cu')
            ->join('unidades as u', 'u.id_unidades', '=', 'cu.id_unidades')
            ->update([
                'cu.unidades_circuitos' => DB::raw('u.unidade'),
            ]);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS sync_circuitos_unidades_after_unidade_update');

            DB::unprepared(<<<'SQL'
CREATE TRIGGER sync_circuitos_unidades_after_unidade_update
AFTER UPDATE ON unidades
FOR EACH ROW
BEGIN
    IF NOT (OLD.unidade <=> NEW.unidade) THEN
        UPDATE circuitos_unidades
        SET unidades_circuitos = NEW.unidade
        WHERE id_unidades = NEW.id_unidades;
    END IF;
END
SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS sync_circuitos_unidades_after_unidade_update');
        }
    }
};

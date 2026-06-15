<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('circuitos_unidades')
            ->where('operadora', 'Copel')
            ->update(['operadora' => 'Ligga']);

        $newUnits = [
            'Marialva V',
            'São Luiz II',
            'Prudentopolis II',
        ];

        foreach ($newUnits as $unitName) {
            $exists = DB::table('unidades')
                ->whereRaw('LOWER(unidade) = ?', [mb_strtolower($unitName)])
                ->exists();

            if (! $exists) {
                DB::table('unidades')->insert([
                    'unidade' => $unitName,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('circuitos_unidades')
            ->where('operadora', 'Ligga')
            ->update(['operadora' => 'Copel']);

        DB::table('unidades')
            ->whereIn('unidade', ['Marialva V', 'São Luiz II', 'Prudentopolis II'])
            ->delete();
    }
};


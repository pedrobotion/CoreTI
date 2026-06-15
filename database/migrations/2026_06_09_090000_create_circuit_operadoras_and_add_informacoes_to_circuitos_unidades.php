<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circuit_operadoras', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('circuitos_unidades', function (Blueprint $table): void {
            $table->text('informacoes_adicionais')->nullable()->after('contato_whatsapp');
        });

        $operadoras = [
            'Rav - Ligga',
            'Oi',
            'Visao Net',
            'Fibercom',
            'Zaaz',
            'Zazz',
            'iSuper',
            'Ligga',
            'Mega',
            'GGNet',
            'Quality Net',
            'Mafra P4 Net',
            'Cybervia',
        ];

        $existentes = DB::table('circuitos_unidades')
            ->whereNotNull('operadora')
            ->where('operadora', '<>', '')
            ->distinct()
            ->pluck('operadora')
            ->all();

        foreach (array_unique(array_merge($operadoras, $existentes)) as $operadora) {
            DB::table('circuit_operadoras')->updateOrInsert(
                ['nome' => trim((string) $operadora)],
                ['ativo' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::table('circuitos_unidades', function (Blueprint $table): void {
            $table->dropColumn('informacoes_adicionais');
        });

        Schema::dropIfExists('circuit_operadoras');
    }
};

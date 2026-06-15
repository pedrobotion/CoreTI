<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('bancada_equipments', 'terceiros_retorno_informado_em')) {
                $table->timestamp('terceiros_retorno_informado_em')->nullable()->after('terceiros_retorno_em');
            }

            if (! Schema::hasColumn('bancada_equipments', 'terceiros_retorno_informado_by')) {
                $table->foreignId('terceiros_retorno_informado_by')
                    ->nullable()
                    ->after('terceiros_retorno_informado_em')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('bancada_equipments', 'terceiros_retorno_fisico_em')) {
                $table->timestamp('terceiros_retorno_fisico_em')->nullable()->after('terceiros_retorno_informado_by');
            }

            if (! Schema::hasColumn('bancada_equipments', 'terceiros_retorno_fisico_by')) {
                $table->foreignId('terceiros_retorno_fisico_by')
                    ->nullable()
                    ->after('terceiros_retorno_fisico_em')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('bancada_equipments', 'terceiros_retorno_fisico_observacao')) {
                $table->text('terceiros_retorno_fisico_observacao')->nullable()->after('terceiros_retorno_fisico_by');
            }
        });

        if (Schema::hasTable('bancada_equipments')) {
            DB::table('bancada_equipments')
                ->whereNotNull('terceiros_retorno_em')
                ->whereNull('terceiros_retorno_informado_em')
                ->update([
                    'terceiros_retorno_informado_em' => DB::raw('terceiros_retorno_em'),
                ]);

            DB::table('bancada_equipments')
                ->where('status', 'Terceiros')
                ->where('terceiros_fluxo_status', 'retorno_positivo')
                ->update([
                    'terceiros_resultado' => 'aprovada',
                    'terceiros_orcamento_status' => 'aprovado',
                    'terceiros_fluxo_status' => 'aguardando_retorno_fisico_aprovado',
                    'terceiros_retorno_informado_em' => DB::raw('COALESCE(terceiros_retorno_informado_em, terceiros_retorno_em)'),
                ]);

            DB::table('bancada_equipments')
                ->where('status', 'Terceiros')
                ->where('terceiros_fluxo_status', 'retorno_negativo')
                ->update([
                    'terceiros_resultado' => 'sem_conserto',
                    'terceiros_orcamento_status' => 'reprovado',
                    'terceiros_fluxo_status' => 'aguardando_retorno_fisico_reprovado',
                    'terceiros_retorno_informado_em' => DB::raw('COALESCE(terceiros_retorno_informado_em, terceiros_retorno_em)'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            if (Schema::hasColumn('bancada_equipments', 'terceiros_retorno_fisico_observacao')) {
                $table->dropColumn('terceiros_retorno_fisico_observacao');
            }
            if (Schema::hasColumn('bancada_equipments', 'terceiros_retorno_fisico_by')) {
                $table->dropConstrainedForeignId('terceiros_retorno_fisico_by');
            }
            if (Schema::hasColumn('bancada_equipments', 'terceiros_retorno_fisico_em')) {
                $table->dropColumn('terceiros_retorno_fisico_em');
            }
            if (Schema::hasColumn('bancada_equipments', 'terceiros_retorno_informado_by')) {
                $table->dropConstrainedForeignId('terceiros_retorno_informado_by');
            }
            if (Schema::hasColumn('bancada_equipments', 'terceiros_retorno_informado_em')) {
                $table->dropColumn('terceiros_retorno_informado_em');
            }
        });
    }
};

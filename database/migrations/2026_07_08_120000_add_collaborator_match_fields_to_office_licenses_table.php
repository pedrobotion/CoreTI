<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('office_licenses', 'centro_custo')) {
                $table->string('centro_custo', 255)->nullable()->after('area_office');
            }

            if (! Schema::hasColumn('office_licenses', 'centro_custo_nome')) {
                $table->string('centro_custo_nome', 255)->nullable()->after('centro_custo');
            }

            if (! Schema::hasColumn('office_licenses', 'colaborador_nome')) {
                $table->string('colaborador_nome', 255)->nullable()->after('centro_custo_nome');
            }

            if (! Schema::hasColumn('office_licenses', 'colaborador_match_score')) {
                $table->decimal('colaborador_match_score', 5, 2)->nullable()->after('colaborador_nome');
            }

            if (! Schema::hasColumn('office_licenses', 'colaborador_match_type')) {
                $table->string('colaborador_match_type', 50)->nullable()->after('colaborador_match_score');
            }

            if (! Schema::hasColumn('office_licenses', 'colaborador_matched_at')) {
                $table->timestamp('colaborador_matched_at')->nullable()->after('colaborador_match_type');
            }

            if (! Schema::hasColumn('office_licenses', 'colaborador_match_status')) {
                $table->string('colaborador_match_status', 50)->nullable()->after('colaborador_matched_at');
            }

            if (! Schema::hasColumn('office_licenses', 'colaborador_match_message')) {
                $table->text('colaborador_match_message')->nullable()->after('colaborador_match_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            $columns = [
                'centro_custo',
                'centro_custo_nome',
                'colaborador_nome',
                'colaborador_match_score',
                'colaborador_match_type',
                'colaborador_matched_at',
                'colaborador_match_status',
                'colaborador_match_message',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('office_licenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

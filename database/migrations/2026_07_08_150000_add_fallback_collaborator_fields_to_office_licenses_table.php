<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('office_licenses', 'cargo_nome')) {
                $table->string('cargo_nome')->nullable()->after('setor_nome');
            }
            if (! Schema::hasColumn('office_licenses', 'empresa_nome')) {
                $table->string('empresa_nome')->nullable()->after('cargo_nome');
            }
            if (! Schema::hasColumn('office_licenses', 'empresa_id_cocari')) {
                $table->string('empresa_id_cocari')->nullable()->after('empresa_nome');
            }
            if (! Schema::hasColumn('office_licenses', 'colaborador_source_view')) {
                $table->string('colaborador_source_view')->nullable()->after('colaborador_nome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            $drop = [];
            foreach (['cargo_nome', 'empresa_nome', 'empresa_id_cocari', 'colaborador_source_view'] as $column) {
                if (Schema::hasColumn('office_licenses', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};

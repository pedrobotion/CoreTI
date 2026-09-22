<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('office_licenses', 'area_nome')) {
                $table->string('area_nome')->nullable()->after('unidade_nome');
            }

            if (! Schema::hasColumn('office_licenses', 'setor_nome')) {
                $table->string('setor_nome')->nullable()->after('departamento_nome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            $drop = [];
            if (Schema::hasColumn('office_licenses', 'area_nome')) {
                $drop[] = 'area_nome';
            }
            if (Schema::hasColumn('office_licenses', 'setor_nome')) {
                $drop[] = 'setor_nome';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};

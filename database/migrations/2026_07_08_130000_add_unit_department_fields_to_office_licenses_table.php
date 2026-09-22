<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('office_licenses', 'unidade_id')) {
                $table->unsignedInteger('unidade_id')->nullable()->after('area_office');
                $table->index('unidade_id');
            }

            if (! Schema::hasColumn('office_licenses', 'departamento_id')) {
                $table->unsignedBigInteger('departamento_id')->nullable()->after('unidade_id');
                $table->index('departamento_id');
            }

            if (! Schema::hasColumn('office_licenses', 'unidade_nome')) {
                $table->string('unidade_nome', 255)->nullable()->after('departamento_id');
            }

            if (! Schema::hasColumn('office_licenses', 'departamento_nome')) {
                $table->string('departamento_nome', 255)->nullable()->after('unidade_nome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            if (Schema::hasColumn('office_licenses', 'departamento_nome')) {
                $table->dropColumn('departamento_nome');
            }
            if (Schema::hasColumn('office_licenses', 'unidade_nome')) {
                $table->dropColumn('unidade_nome');
            }
            if (Schema::hasColumn('office_licenses', 'departamento_id')) {
                $table->dropIndex(['departamento_id']);
                $table->dropColumn('departamento_id');
            }
            if (Schema::hasColumn('office_licenses', 'unidade_id')) {
                $table->dropIndex(['unidade_id']);
                $table->dropColumn('unidade_id');
            }
        });
    }
};

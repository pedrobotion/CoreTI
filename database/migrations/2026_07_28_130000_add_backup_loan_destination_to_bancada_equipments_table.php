<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bancada_equipments')) {
            return;
        }

        Schema::table('bancada_equipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('bancada_equipments', 'backup_emprestado_para')) {
                $table->string('backup_emprestado_para', 255)->nullable()->after('backup_localizacao');
            }
            if (! Schema::hasColumn('bancada_equipments', 'backup_emprestado_em')) {
                $table->timestamp('backup_emprestado_em')->nullable()->after('backup_emprestado_para');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bancada_equipments')) {
            return;
        }

        Schema::table('bancada_equipments', function (Blueprint $table): void {
            if (Schema::hasColumn('bancada_equipments', 'backup_emprestado_em')) {
                $table->dropColumn('backup_emprestado_em');
            }
            if (Schema::hasColumn('bancada_equipments', 'backup_emprestado_para')) {
                $table->dropColumn('backup_emprestado_para');
            }
        });
    }
};

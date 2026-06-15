<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('bancada_equipments', 'backup_localizacao')) {
                $table->string('backup_localizacao', 255)->nullable()->after('tic');
            }
            if (! Schema::hasColumn('bancada_equipments', 'backup_pronto_emprestimo')) {
                $table->boolean('backup_pronto_emprestimo')->default(false)->after('backup_localizacao');
            }
            if (! Schema::hasColumn('bancada_equipments', 'backup_data_formatado')) {
                $table->date('backup_data_formatado')->nullable()->after('backup_pronto_emprestimo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            if (Schema::hasColumn('bancada_equipments', 'backup_data_formatado')) {
                $table->dropColumn('backup_data_formatado');
            }
            if (Schema::hasColumn('bancada_equipments', 'backup_pronto_emprestimo')) {
                $table->dropColumn('backup_pronto_emprestimo');
            }
            if (Schema::hasColumn('bancada_equipments', 'backup_localizacao')) {
                $table->dropColumn('backup_localizacao');
            }
        });
    }
};


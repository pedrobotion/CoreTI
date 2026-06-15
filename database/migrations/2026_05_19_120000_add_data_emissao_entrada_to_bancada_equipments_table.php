<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->date('data_emissao_entrada')->nullable()->after('nota_numero_entrada');
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->dropColumn('data_emissao_entrada');
        });
    }
};


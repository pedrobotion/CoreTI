<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Um mesmo centro de custo pode ter várias unidades com o mesmo código.
 *
 * Ex.: as lojas Colari compartilham o código 01.316. O índice único só no
 * `code` impedia cadastrar mais de uma. Passa a ser único por (code, name):
 * o mesmo código pode repetir com nomes diferentes, mas não o par exato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coreti_cost_centers', function (Blueprint $table) {
            $table->dropUnique('coreti_cost_centers_code_unique');
            $table->unique(['code', 'name'], 'coreti_cost_centers_code_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('coreti_cost_centers', function (Blueprint $table) {
            $table->dropUnique('coreti_cost_centers_code_name_unique');
            $table->unique('code', 'coreti_cost_centers_code_unique');
        });
    }
};

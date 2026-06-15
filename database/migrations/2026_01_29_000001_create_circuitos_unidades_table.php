<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circuitos_unidades', function (Blueprint $table) {
            $table->increments('id_circuitos');
            $table->string('operadora', 50);
            $table->string('unidades_circuitos', 100);
            $table->string('uf', 10);
            $table->string('servico', 255);
            $table->string('endereco', 255);
            $table->string('contato', 50);
            $table->integer('id_unidades')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuitos_unidades');
    }
};

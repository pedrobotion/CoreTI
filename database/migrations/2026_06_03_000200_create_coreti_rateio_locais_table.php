<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coreti_rateio_locais', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_local', 50)->index();
            $table->string('nome_local', 255);
            $table->string('nome_normalizado', 255)->nullable()->index();
            $table->string('unicoop', 10)->nullable()->index();
            $table->string('area', 20)->nullable()->index();
            $table->string('centro_custo', 50)->nullable()->index();
            $table->string('centro_custo_nome', 255)->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(['tipo_local', 'nome_normalizado', 'centro_custo'], 'coreti_rateio_locais_tipo_nome_centro_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coreti_rateio_locais');
    }
};

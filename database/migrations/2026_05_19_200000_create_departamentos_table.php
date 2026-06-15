<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 255);
            $table->string('unicoop', 10)->nullable();
            $table->string('area', 50)->nullable();
            $table->boolean('ativo')->default(true);
            $table->string('origem', 80)->nullable();
            $table->timestamps();

            $table->index('nome');
            $table->index('unicoop');
            $table->index('area');
            $table->unique(['nome', 'unicoop', 'area'], 'departamentos_nome_unicoop_area_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};


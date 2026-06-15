<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sede_departamentos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome_departamento', 255);
            $table->string('unicoop', 10)->default('01');
            $table->string('area', 50)->nullable();
            $table->boolean('ativo')->default(true);
            $table->string('origem', 80)->nullable();
            $table->timestamps();

            $table->unique('nome_departamento', 'sede_departamentos_nome_unique');
            $table->index('unicoop');
            $table->index('area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sede_departamentos');
    }
};


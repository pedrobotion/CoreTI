<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_produtos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('nome', 'network_produtos_nome_unique');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_produtos');
    }
};

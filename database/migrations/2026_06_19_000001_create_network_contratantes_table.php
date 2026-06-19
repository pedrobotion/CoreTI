<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_contratantes', function (Blueprint $table): void {
            $table->id();
            $table->string('nome_fantasia')->nullable();
            $table->string('razao_social');
            $table->string('cnpj', 20);
            $table->string('inscricao_estadual')->nullable();
            $table->text('observacao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('cnpj', 'network_contratantes_cnpj_unique');
            $table->index('razao_social');
            $table->index('nome_fantasia');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_contratantes');
    }
};

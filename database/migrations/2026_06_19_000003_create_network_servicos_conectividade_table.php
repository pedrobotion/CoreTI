<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_servicos_conectividade', function (Blueprint $table): void {
            $table->id();

            $table->string('contrato')->nullable();
            $table->foreignId('contratante_id')->nullable()->constrained('network_contratantes')->nullOnDelete();
            $table->string('razao_social')->nullable();

            $table->string('velocidade_nominal')->nullable();
            $table->foreignId('produto_id')->nullable()->constrained('network_produtos')->nullOnDelete();

            $table->string('rede')->nullable();
            $table->string('mascara')->nullable();
            $table->string('gateway')->nullable();
            $table->string('broadcast')->nullable();
            $table->string('faixa')->nullable();
            $table->string('qtd')->nullable();

            $table->text('endereco_instalacao')->nullable();

            $table->string('estacao_a')->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero')->nullable();
            $table->string('bairro')->nullable();
            $table->string('municipio')->nullable();
            $table->string('uf', 10)->nullable();

            $table->string('contato')->nullable();
            $table->text('outros_contatos')->nullable();

            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->unsignedBigInteger('departamento_id')->nullable();
            $table->string('local_tipo')->nullable();
            $table->string('local_nome')->nullable();

            $table->text('observacao')->nullable();
            $table->string('status', 20)->default('ativo');

            $table->string('ficha_original_name')->nullable();
            $table->string('ficha_storage_path')->nullable();
            $table->string('ficha_mime_type')->nullable();
            $table->timestamp('ficha_uploaded_at')->nullable();
            $table->foreignId('ficha_uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('ia_status')->nullable();
            $table->json('ia_resultado')->nullable();
            $table->timestamp('ia_processado_em')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('contrato');
            $table->index('status');
            $table->index('municipio');
            $table->index('local_tipo');
            $table->index('local_nome');
            $table->index('unidade_id');
            $table->index('departamento_id');
            $table->index('produto_id');
            $table->index('contratante_id');
            $table->index('ficha_storage_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_servicos_conectividade');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lotes mensais de expurgo de contas do Active Directory.
 *
 * O servidor do AD monta a prévia e envia para cá; ninguém é excluído antes de
 * um humano aprovar nesta tabela. O privilégio de apagar continua no servidor
 * do AD — o CoreTI guarda a decisão, não a credencial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_purge_batches', function (Blueprint $table): void {
            $table->id();

            // Competência do lote (ex.: 2026-10). Um lote por mês.
            $table->string('referencia', 7)->unique();

            $table->string('status', 24)->default('aguardando_aprovacao')->index();

            // Como a prévia foi montada, para auditoria.
            $table->unsignedSmallInteger('dias_criterio');
            $table->string('origem_host')->nullable();
            $table->string('search_base')->nullable();

            $table->unsignedInteger('total_candidatos')->default(0);
            $table->unsignedInteger('total_aprovados')->default(0);
            $table->unsignedInteger('total_excluidos')->default(0);

            $table->foreignId('aprovado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable();
            $table->foreignId('rejeitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejeitado_em')->nullable();

            // Depois disso a aprovação não vale mais: uma lista de setembro não
            // pode ser executada em dezembro, quando as contas já mudaram.
            $table->timestamp('expira_em')->nullable()->index();

            $table->timestamp('executado_em')->nullable();
            $table->text('observacao')->nullable();

            $table->timestamps();
        });

        Schema::create('ad_purge_batch_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('batch_id')->constrained('ad_purge_batches')->cascadeOnDelete();

            $table->string('nome_usuario')->index();
            $table->string('nome_exibicao')->nullable();
            $table->string('email')->nullable();
            $table->text('distinguished_name');

            $table->timestamp('desativada_em')->nullable();
            $table->unsignedSmallInteger('dias_desativada')->nullable();
            $table->timestamp('ultimo_logon')->nullable();
            $table->string('descricao')->nullable();

            // Sinalizada pelo script como possível conta de serviço.
            $table->boolean('parece_servico')->default(false);

            // pendente → aprovado/recusado → excluido/pulado/falhou
            $table->string('status', 16)->default('pendente')->index();

            $table->timestamp('excluido_em')->nullable();
            $table->string('resultado_detalhe')->nullable();

            $table->timestamps();

            $table->unique(['batch_id', 'nome_usuario']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_purge_batch_items');
        Schema::dropIfExists('ad_purge_batches');
    }
};

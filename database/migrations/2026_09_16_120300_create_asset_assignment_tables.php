<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo de itens com colaborador — unifica 'vinculos' e 'vinculo_notebooks'
 * do gestaoTI, que eram dois controllers de ~1.600 linhas fazendo a mesma
 * coisa com nomes diferentes.
 *
 * O vínculo é o que se assina; os itens são o que ele cobre. Essa separação
 * existe porque um vínculo "completo" do gestaoTI amarra aparelho + linha num
 * registro só, com UM termo e UM desvínculo para os dois. Uma tabela achatada
 * com uma linha por item quebraria isso: viraria dois termos e duas
 * assinaturas para a mesma entrega.
 *
 * Com isso o 'tipo_vinculo' deixa de ser campo digitado e passa a ser lido do
 * conteúdo — não há mais como gravar 'completo' sem aparelho.
 *
 * O colaborador vem de hr_collaborators (sincronizado direto do MySQL da
 * intranet). A matrícula fica copiada junto porque o termo é um documento
 * histórico: precisa continuar legível mesmo que o cadastro mude depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20)->index();

            $table->foreignId('hr_collaborator_id')->constrained('hr_collaborators')->restrictOnDelete();
            $table->string('collaborator_matricula', 100)->nullable()->index();

            $table->dateTime('data_vinculo');
            $table->date('data_vencimento')->index();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true)->index();

            $table->string('termo_assinado_path')->nullable();
            $table->dateTime('termo_assinado_em')->nullable();

            $table->string('motivo_desvinculo')->nullable();
            $table->text('observacoes_desvinculo')->nullable();
            $table->dateTime('data_desvinculo')->nullable();
            $table->foreignId('desvinculado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'ativo']);
            $table->index(['hr_collaborator_id', 'ativo']);
        });

        /*
         | Três colunas nomeadas em vez de um morph (item_type/item_id).
         |
         | O conjunto de itens é fechado e conhecido, e a coluna nomeada deixa
         | o banco impor o que mais importa aqui: restrictOnDelete impede
         | apagar uma linha, um aparelho ou um equipamento que ainda esteja
         | vinculado. No gestaoTI as FKs são ON DELETE CASCADE — excluir um
         | celular apaga junto o histórico de vínculo dele, sem aviso. Um morph
         | não teria FK nenhuma e deixaria essa garantia só no código.
         */
        Schema::create('asset_assignment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_assignment_id')->constrained('asset_assignments')->cascadeOnDelete();

            $table->foreignId('corp_line_id')->nullable()->constrained('corp_lines')->restrictOnDelete();
            $table->foreignId('corp_device_id')->nullable()->constrained('corp_devices')->restrictOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipments')->restrictOnDelete();

            $table->timestamps();

            // O mesmo item não se repete dentro de um vínculo. "Um item em um
            // só vínculo ativo" depende de 'ativo' e o MySQL não indexa
            // condição — essa regra é aplicada no model, como o gestaoTI já
            // fazia ao conferir se a linha tinha vínculo ativo.
            $table->unique(['asset_assignment_id', 'corp_line_id']);
            $table->unique(['asset_assignment_id', 'corp_device_id']);
            $table->unique(['asset_assignment_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignment_items');
        Schema::dropIfExists('asset_assignments');
    }
};

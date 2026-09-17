<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faturas de telefonia importadas do PDF da operadora.
 *
 * A fatura guarda o documento e os totais; cada item guarda o que veio para
 * uma linha naquela competência, com o valor anterior e a variação — é o que
 * permite responder "por que a conta subiu este mês?" olhando linha a linha.
 *
 * Os itens mantêm DDD e número copiados da fatura, além do vínculo com a
 * linha cadastrada. Duas razões: a fatura pode trazer número que ainda não
 * está cadastrado (e isso precisa aparecer, não sumir), e o histórico de
 * cobrança continua legível mesmo que a linha seja excluída depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telefonia_faturas', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20)->index();
            $table->string('operadora', 50)->index();
            $table->string('numero_conta', 60)->nullable()->index();
            $table->date('competencia')->nullable()->index();
            $table->date('vencimento')->nullable();
            $table->decimal('valor_total_declarado', 12, 2)->nullable();
            $table->decimal('valor_total_calculado', 12, 2)->nullable();
            $table->unsignedInteger('quantidade_linhas')->default(0);
            $table->string('arquivo_pdf')->nullable();
            $table->string('arquivo_nome_original')->nullable();
            // Impede importar duas vezes o mesmo PDF sem que ninguém perceba.
            $table->string('hash_arquivo', 64)->nullable()->unique();
            $table->string('status_processamento', 30)->default('processado');
            $table->text('observacao_parser')->nullable();
            $table->foreignId('importado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'competencia']);
            $table->index(['operadora', 'competencia']);
        });

        Schema::create('telefonia_fatura_linhas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telefonia_fatura_id')->constrained('telefonia_faturas')->cascadeOnDelete();
            // nullOnDelete, não restrict: o histórico de cobrança sobrevive à
            // exclusão da linha porque DDD e número ficam copiados aqui.
            $table->foreignId('corp_line_id')->nullable()->constrained('corp_lines')->nullOnDelete();
            $table->date('competencia')->nullable()->index();
            $table->string('ddd', 3);
            $table->string('numero', 20);
            $table->string('nome_plano')->nullable();
            $table->decimal('valor', 12, 2)->default(0);
            $table->decimal('valor_anterior', 12, 2)->nullable();
            $table->decimal('variacao_percentual', 8, 2)->nullable();
            $table->boolean('linha_nova')->default(false)->index();
            $table->timestamps();

            $table->index(['corp_line_id', 'competencia']);
            $table->index(['ddd', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telefonia_fatura_linhas');
        Schema::dropIfExists('telefonia_faturas');
    }
};

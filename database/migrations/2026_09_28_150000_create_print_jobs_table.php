<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trabalhos de impressão vindos do log do Windows PrintService (evento 307).
 *
 * Uma linha por trabalho. Todas as visões do relatório — por usuário, por
 * impressora, por dia, por horário — são agregações desta tabela, então não
 * há tabela de resumo: resumo que se guarda desatualiza e diverge da origem.
 *
 * O log do Windows gira e apaga os eventos antigos. Copiar para cá é
 * justamente o que permite estatística de período longo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table): void {
            $table->id();

            // Qual servidor de impressão entregou o evento (IMP01/02/03).
            $table->string('servidor', 40)->index();

            /*
             | Impressão de um evento, para não duplicar em reenvio.
             |
             | O script pode rodar de novo sobre a mesma janela — por retomada
             | após falha, por sobreposição das execuções ou por alguém
             | conferindo. O número do trabalho recicla dentro do servidor, e
             | por isso sozinho não identifica: a chave junta servidor,
             | trabalho, horário e usuário.
             */
            $table->string('chave_evento', 64)->unique();

            $table->timestamp('impresso_em')->index();
            $table->string('usuario', 120)->index();
            $table->string('estacao', 120)->nullable()->index();
            $table->string('impressora', 160)->index();
            $table->string('porta', 120)->nullable();

            // Número do trabalho na fila do spool. Recicla; não é identidade.
            $table->string('doc_numero', 40)->nullable();

            /*
             | O log às vezes traz 0 página: cancelamento na fila ou trabalho
             | que não chegou a render folha. Guardamos como veio, porque
             | descartar esconderia desperdício que vale medir.
             */
            $table->unsignedInteger('paginas')->default(0);
            $table->unsignedBigInteger('tamanho_bytes')->nullable();

            /*
             | Vínculo com a impressora do Insight, quando o nome casa.
             |
             | Fica nulo quando não casa: 22 das 61 impressoras do relatório
             | não estão no portal. Nulo é informação — diz que aquele
             | equipamento imprime mas não é monitorado.
             */
            $table->foreignId('printer_id')->nullable()->constrained('printers')->nullOnDelete();

            $table->json('raw_data')->nullable();
            $table->timestamps();

            // O dashboard filtra por período e agrupa por usuário/impressora.
            $table->index(['impresso_em', 'impressora']);
            $table->index(['impresso_em', 'usuario']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};

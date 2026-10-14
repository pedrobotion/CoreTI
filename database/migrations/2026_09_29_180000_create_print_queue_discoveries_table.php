<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filas de impressão vistas nos servidores IMP01/02/03, com o serial lido por
 * SNMP e o resultado do casamento com a base da Simpress.
 *
 * Separada de `printers` de propósito: printers é o espelho do portal da
 * Simpress (só leitura, sobrescrito a cada sincronização). Esta tabela é o que
 * NÓS observamos na rede — a fonte independente que serve para conferir a
 * Simpress, não para ser sobrescrita por ela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_queue_discoveries', function (Blueprint $table): void {
            $table->id();

            // De qual servidor de impressão veio a fila.
            $table->string('servidor', 40)->index();

            // Nome da fila e IP configurado na porta TCP/IP do Windows.
            $table->string('fila', 160);
            $table->string('ip_porta', 45)->nullable()->index();
            $table->string('driver', 200)->nullable();

            /*
             | Serial lido por SNMP direto no equipamento. Nulo enquanto não foi
             | consultado ou quando a impressora não respondeu.
             */
            $table->string('serial_number', 120)->nullable()->index();
            $table->string('modelo_snmp', 160)->nullable();

            /*
             | Resultado da última consulta SNMP, para o painel explicar o
             | silêncio: 'ok', 'sem_resposta', 'sem_serial', 'nao_consultado'.
             */
            $table->string('snmp_status', 20)->default('nao_consultado');
            $table->string('snmp_detalhe', 255)->nullable();
            $table->timestamp('snmp_verificado_em')->nullable();

            /*
             | A impressora da Simpress que casou pelo serial. Nulo quando o
             | serial não existe na base — impressora em servidor que a Simpress
             | não inventaria.
             */
            $table->foreignId('printer_id')->nullable()->constrained('printers')->nullOnDelete();

            /*
             | Divergência de IP: o que a Simpress registra difere do que o
             | servidor usa de fato. É o achado que motivou tudo — 112
             | impressoras com IP errado ou 127.0.0.1 no portal.
             */
            $table->boolean('ip_divergente')->default(false)->index();
            $table->string('ip_simpress', 45)->nullable();

            $table->timestamp('visto_em')->nullable();
            $table->timestamps();

            // Uma fila é única por servidor: o mesmo nome pode existir em dois.
            $table->unique(['servidor', 'fila']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_queue_discoveries');
    }
};

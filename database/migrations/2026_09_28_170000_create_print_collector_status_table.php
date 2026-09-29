<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Último contato de cada servidor de impressão.
 *
 * Não dá para deduzir isso dos trabalhos: fim de semana e feriado têm zero
 * impressão legitimamente, e "sem evento novo" não distingue silêncio normal
 * de coletor parado. O que responde é quando o servidor FALOU conosco, mesmo
 * que tenha falado para dizer que não havia nada.
 *
 * Importa porque o log do Windows retém cerca de seis dias: coleta parada
 * por mais que isso vira perda definitiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_collector_status', function (Blueprint $table): void {
            $table->id();
            $table->string('servidor', 40)->unique();
            $table->timestamp('ultimo_contato_em')->index();
            $table->unsignedInteger('ultimo_lote_recebidos')->default(0);
            $table->unsignedInteger('ultimo_lote_gravados')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_collector_status');
    }
};

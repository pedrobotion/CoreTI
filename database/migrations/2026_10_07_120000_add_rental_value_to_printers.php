<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valor de locação mensal da impressora (fixo por equipamento, da fatura
 * Simpress — coluna "Valor Mensal Item" do pré-faturamento, casada por
 * número de série).
 *
 * Fica separado do custo variável de impressão (medidores) para o dashboard
 * poder somar os dois e mostrar o custo total real. Nulo = impressora que não
 * aparece na fatura importada (contrato/competência diferente ou sem custo
 * conhecido) — melhor em branco e visível como pendência do que zero fingindo
 * ser "sem custo".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table): void {
            $table->decimal('rental_value', 10, 2)->nullable()->after('sku');
            $table->timestamp('rental_value_updated_at')->nullable()->after('rental_value');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table): void {
            $table->dropColumn(['rental_value', 'rental_value_updated_at']);
        });
    }
};

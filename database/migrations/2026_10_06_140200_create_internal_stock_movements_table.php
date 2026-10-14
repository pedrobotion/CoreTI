<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de movimentações do estoque interno.
 *
 * Toda entrada, baixa ou ajuste gera uma linha aqui — é o extrato do estoque.
 * saldo_apos guarda o saldo do produto logo após o movimento, para o histórico
 * ser auditável sem recomputar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_stock_product_id')->constrained('internal_stock_products')->cascadeOnDelete();
            $table->foreignId('internal_stock_unit_id')->nullable()->constrained('internal_stock_units')->nullOnDelete();
            $table->string('tipo', 20);          // entrada | baixa | ajuste
            $table->integer('quantidade');       // magnitude (sempre positiva)
            $table->string('motivo', 60)->nullable(); // compra | entrega_colaborador | uso_bancada | descarte | devolucao | ajuste
            $table->string('destino_tipo', 20)->nullable(); // colaborador | unidade | bancada
            $table->foreignId('destino_colaborador_id')->nullable()->constrained('hr_collaborators')->nullOnDelete();
            $table->string('destino_descricao', 255)->nullable();
            $table->string('referencia', 120)->nullable();
            $table->integer('saldo_apos')->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tipo');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_stock_movements');
    }
};

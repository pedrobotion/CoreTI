<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unidades físicas dos produtos serializados (monitores, computadores).
 *
 * Uma linha por peça única. O saldo em estoque de um produto 'serial' é a
 * contagem das unidades com status 'em_estoque'. Produtos por quantidade (SSD)
 * NÃO usam esta tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_stock_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_stock_product_id')->constrained('internal_stock_products')->cascadeOnDelete();
            $table->string('serial_number', 120)->nullable();
            $table->string('plaqueta', 100)->nullable();
            $table->string('modelo', 160)->nullable();
            $table->string('estado', 20)->default('Usado');       // Novo | Usado
            $table->string('status', 20)->default('em_estoque');  // em_estoque | entregue | baixado
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('serial_number');
            $table->index('plaqueta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_stock_units');
    }
};

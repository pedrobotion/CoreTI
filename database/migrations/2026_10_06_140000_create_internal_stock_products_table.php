<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo do estoque interno da TI.
 *
 * Cada linha é um "tipo" de item mantido em estoque. O 'controle' define como
 * o saldo é apurado: 'serial' (monitores/computadores — uma unidade física por
 * peça, em internal_stock_units) ou 'quantidade' (SSD e consumíveis — saldo
 * numérico em quantidade_atual). O mínimo mora aqui, e o alerta de estoque
 * baixo compara o saldo com ele.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_stock_products', function (Blueprint $table) {
            $table->id();
            $table->string('categoria', 40);                 // Monitor | Computador | SSD | Outro
            $table->string('nome', 255);
            $table->string('controle', 20)->default('serial'); // serial | quantidade
            $table->unsignedInteger('quantidade_minima')->default(0);
            $table->unsignedInteger('quantidade_atual')->default(0); // usado só no controle 'quantidade'
            $table->boolean('ativo')->default(true);
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('categoria');
            $table->index('controle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_stock_products');
    }
};

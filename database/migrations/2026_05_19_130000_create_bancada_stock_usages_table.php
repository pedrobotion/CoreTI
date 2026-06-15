<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancada_stock_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bancada_equipment_id')->constrained('bancada_equipments')->cascadeOnDelete();
            $table->string('plaqueta', 100)->index();
            $table->string('unidade_setor', 255)->nullable();
            $table->string('peca_nome', 255);
            $table->unsignedInteger('quantidade');
            $table->string('origem', 40)->default('estoque_ti')->index();
            $table->timestamp('used_at');
            $table->string('status', 30)->default('pendente_debito')->index();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancada_stock_usages');
    }
};


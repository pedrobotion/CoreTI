<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linhas corporativas e aparelhos celulares.
 *
 * Duas diferenças deliberadas em relação ao gestaoTI:
 *
 * 1. status e operadora são string, não ENUM. Acrescentar o status 'Cancelada'
 *    lá exigiu um ALTER TABLE; aqui a lista vive em constante no model e a
 *    validação a aplica, então incluir uma operadora nova é editar um array.
 *
 * 2. número da linha e IMEI são únicos. Hoje a base do gestaoTI tem quatro
 *    números repetidos e um IMEI em dois aparelhos, porque nada impedia. Um
 *    número de telefone e um IMEI são únicos no mundo real, então a unicidade
 *    é global e não por escopo — inclusive para flagrar a mesma linha
 *    cadastrada na Sede e no Cerrado ao mesmo tempo, que é o caso do
 *    (61) 99658-7996. A importação vai relatar esses conflitos em vez de
 *    escondê-los.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corp_lines', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20)->index();
            $table->string('operadora', 50)->index();
            $table->string('ddd', 3);
            $table->string('numero', 20);
            $table->string('status', 30)->default('Disponível')->index();
            $table->date('data_cancelamento')->nullable();
            $table->string('nome_plano')->nullable();
            $table->decimal('valor_mensal', 10, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ddd', 'numero']);
            $table->index(['scope', 'status']);
        });

        Schema::create('corp_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20)->index();
            $table->string('marca', 100)->index();
            $table->string('modelo', 100);
            $table->string('imei_1', 20)->unique();
            $table->string('imei_2', 20)->nullable();
            $table->string('numero_serie', 50)->nullable();
            $table->string('status', 30)->default('Disponível')->index();
            $table->string('estado_aparelho', 20)->default('Usado')->index();
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corp_devices');
        Schema::dropIfExists('corp_lines');
    }
};

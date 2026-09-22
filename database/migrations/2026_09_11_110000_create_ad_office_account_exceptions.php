<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas que a regra de Escritório não toca, mesmo dentro de uma OU definida.
 *
 * A exceção não é sempre da OU inteira: há pessoas cujo centro de custo segue
 * a função e não o lugar, e contas tratadas manualmente caso a caso — o setor
 * de Power BI é assim, o custo depende de quem usa.
 *
 * Fica em tabela, e não em arquivo de configuração, porque a lista muda com a
 * operação: cadastrar uma exceção não pode exigir deploy, e cada uma precisa
 * do motivo registrado junto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_office_account_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->string('nome_usuario')->unique();
            $table->string('motivo');
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_office_account_exceptions');
    }
};

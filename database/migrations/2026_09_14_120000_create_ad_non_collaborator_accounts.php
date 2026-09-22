<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas do AD que não são de colaborador.
 *
 * Balança, barraca, impressora, guarita, terminal de recepção, conta de
 * serviço e conta de máquina existem no domínio mas não são pessoas: não têm
 * matrícula nem cargo, e nunca terão.
 *
 * Fica em tabela, e não em padrão de nome no código, porque a lista não segue
 * regra: `atak01`, `caixa`, `trr` e `lascocari` não compartilham prefixo algum.
 * Uma lista de 50 padrões seria frágil e exigiria deploy a cada conta nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_non_collaborator_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('nome_usuario')->unique();
            $table->string('motivo')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_non_collaborator_accounts');
    }
};

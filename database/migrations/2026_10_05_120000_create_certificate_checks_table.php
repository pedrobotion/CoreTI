<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Última verificação de cada certificado monitorado.
 *
 * Uma linha por host. Guarda a data de expiração vista e o chamado aberto,
 * para o aviso de renovação sair UMA vez por ciclo de certificado — e não a
 * cada execução do monitor enquanto a validade segue dentro da janela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('host', 190)->unique();
            $table->timestamp('expira_em')->nullable();

            // Impressão digital do certificado. Muda quando ele é renovado —
            // é assim que o monitor sabe que pode abrir chamado de novo no
            // próximo ciclo, em vez de repetir para o mesmo certificado.
            $table->string('fingerprint', 100)->nullable();

            $table->string('status', 20)->default('desconhecido');
            $table->string('detalhe', 255)->nullable();
            $table->timestamp('verificado_em')->nullable();

            // Chamado de renovação já aberto para ESTE certificado.
            $table->string('jira_issue_key', 40)->nullable();
            $table->timestamp('jira_aberto_em')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_checks');
    }
};

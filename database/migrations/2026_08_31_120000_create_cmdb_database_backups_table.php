<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria de execução de backups de banco de dados.
 *
 * Equivale à tabela backup_auditoria (MySQL 172.17.3.97) alimentada pelo n8n,
 * com três acréscimos:
 *   - vínculo opcional com o ativo do CMDB, resolvido pelo hostname;
 *   - fingerprint único, para que reenvio do agente não duplique registro
 *     nem gere um segundo chamado;
 *   - payload bruto, para diagnosticar divergência de contrato do agente.
 *
 * Não confundir com cmdb_asset_backups, que guarda estado de proteção Veeam
 * por VM — aqui é evento de execução por banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cmdb_database_backups', function (Blueprint $table): void {
            $table->id();

            $table->string('host', 191)->index();
            $table->string('database_name', 191)->index();
            $table->string('backup_type', 60)->nullable()->index();
            $table->string('status', 40)->index();
            $table->boolean('sucesso')->default(false)->index();

            $table->string('ip_address', 45)->nullable();
            $table->string('arquivo', 512)->nullable();
            $table->decimal('tamanho', 14, 3)->nullable();
            $table->text('observacao')->nullable();

            $table->timestamp('inicio_backup')->nullable();
            $table->timestamp('fim_backup')->nullable();
            $table->timestamp('data_backup')->nullable()->index();
            $table->timestamp('data_verificacao')->nullable();
            $table->unsignedInteger('duracao_segundos')->nullable();

            // Vínculo com o CMDB. Nulo quando o host não casa com nenhum ativo.
            $table->foreignId('asset_id')->nullable()->constrained('cmdb_assets')->nullOnDelete();
            $table->string('asset_match_method', 40)->nullable();

            $table->string('jira_issue_key', 64)->nullable()->index();
            $table->string('jira_issue_url')->nullable();
            $table->timestamp('jira_dispatched_at')->nullable();
            $table->text('jira_error')->nullable();

            $table->json('payload')->nullable();
            $table->string('fingerprint', 64)->unique();
            $table->timestamp('received_at')->nullable()->index();

            $table->timestamps();

            // Consulta principal da tela: último evento por host+banco.
            $table->index(['host', 'database_name', 'data_backup'], 'cmdb_db_backups_host_db_data');
            $table->index(['sucesso', 'data_backup']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_database_backups');
    }
};

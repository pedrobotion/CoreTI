<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificação ativa de backup: o CoreTI confere o arquivo por SSH, em vez de
 * confiar apenas no que o agente reporta sobre si mesmo.
 *
 * São duas fontes independentes. O agente sabe o resultado do processo; o
 * CoreTI sabe se o artefato existe, está fresco e tem tamanho plausível.
 * Quando as duas discordam ("script disse OK, arquivo está de ontem"), é
 * exatamente o caso que nenhuma das duas pegaria sozinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guardas para que uma falha no meio não deixe estado parcial que
        // impeça a reexecução.
        if (! Schema::hasTable('cmdb_backup_targets')) {
        Schema::create('cmdb_backup_targets', function (Blueprint $table): void {
            $table->id();

            $table->string('host', 191);
            $table->string('database_name', 191);
            $table->string('arquivo', 512);

            // Conexão. Nulo usa o padrão de config/backups.php.
            $table->string('ssh_host', 191)->nullable();
            $table->string('ssh_user', 100)->nullable();
            $table->unsignedSmallInteger('ssh_port')->nullable();

            // Limites. Nulos usam o padrão global ou a média histórica.
            $table->unsignedBigInteger('min_tamanho_bytes')->nullable();
            $table->unsignedSmallInteger('max_idade_horas')->nullable();

            $table->boolean('ativo')->default(true)->index();
            $table->string('origem_cadastro', 20)->default('auto');
            $table->text('observacao')->nullable();

            $table->timestamp('last_checked_at')->nullable()->index();
            $table->string('last_status', 20)->nullable()->index();

            $table->foreignId('asset_id')->nullable()->constrained('cmdb_assets')->nullOnDelete();
            $table->timestamps();

            /*
             | Unicidade por hash de (host, banco, arquivo): o índice composto
             | dessas três colunas estoura o limite de 3072 bytes do utf8mb4.
             */
            $table->char('chave', 64)->unique();
        });
        }

        if (Schema::hasTable('cmdb_backup_checks')) {
            return;
        }

        Schema::create('cmdb_backup_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('target_id')->constrained('cmdb_backup_targets')->cascadeOnDelete();

            $table->timestamp('executado_em')->index();

            /*
             | ok          -> arquivo presente, fresco e com tamanho plausível
             | ausente     -> arquivo não encontrado
             | velho       -> existe, mas mais antigo que o limite
             | pequeno     -> existe e fresco, mas menor que o mínimo
             | inacessivel -> não deu para conectar/ler
             */
            $table->string('status', 20)->index();
            $table->text('detalhe')->nullable();

            $table->boolean('arquivo_existe')->default(false);
            $table->unsignedBigInteger('arquivo_tamanho_bytes')->nullable();
            $table->timestamp('arquivo_modificado_em')->nullable();
            $table->unsignedInteger('idade_horas')->nullable();

            /*
             | Verdadeiro quando o agente reportou sucesso recente mas o arquivo
             | não confirma. É o achado que justifica a verificação ativa.
             */
            $table->boolean('divergente')->default(false)->index();
            $table->text('divergencia_detalhe')->nullable();

            $table->string('jira_issue_key', 64)->nullable();
            $table->string('jira_issue_url')->nullable();
            $table->timestamp('jira_dispatched_at')->nullable();

            $table->timestamps();

            $table->index(['target_id', 'executado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_backup_checks');
        Schema::dropIfExists('cmdb_backup_targets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes da revisão da auditoria autônoma. Incremental: nada é recriado.
 *
 * cmdb_backup_targets
 *   ultima_janela_auditada  — qual janela esperada já foi auditada pelo
 *                             scheduler. Separado de last_checked_at para que
 *                             uma verificação manual não desloque o ciclo.
 *   monitorar_a_partir_de   — evita falso positivo em alvo recém-cadastrado:
 *                             janelas anteriores a esta data são ignoradas.
 *   log_path                — caminho do log da tecnologia (ex.: .lg do
 *                             Progress). Opcional; sem ele, audita só o artefato.
 *
 * cmdb_backup_incidents
 *   encerrado_em / motivo   — encerramento genérico. 'recuperado_em' passa a
 *                             significar exclusivamente retorno real a OK.
 *   jira_ultima_tentativa_em / jira_proxima_tentativa_em
 *                           — cooldown temporal de verdade, em vez de inferir
 *                             a janela pelo valor textual de jira_status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cmdb_backup_targets', function (Blueprint $table): void {
            if (! Schema::hasColumn('cmdb_backup_targets', 'ultima_janela_auditada')) {
                $table->timestamp('ultima_janela_auditada')->nullable()->after('last_status')->index();
            }
            if (! Schema::hasColumn('cmdb_backup_targets', 'monitorar_a_partir_de')) {
                $table->timestamp('monitorar_a_partir_de')->nullable()->after('ultima_janela_auditada');
            }
            if (! Schema::hasColumn('cmdb_backup_targets', 'log_path')) {
                $table->string('log_path', 512)->nullable()->after('arquivo');
            }
        });

        Schema::table('cmdb_backup_incidents', function (Blueprint $table): void {
            if (! Schema::hasColumn('cmdb_backup_incidents', 'encerrado_em')) {
                $table->timestamp('encerrado_em')->nullable()->after('recuperado_em');
            }
            if (! Schema::hasColumn('cmdb_backup_incidents', 'motivo_encerramento')) {
                $table->string('motivo_encerramento', 40)->nullable()->after('encerrado_em');
            }
            if (! Schema::hasColumn('cmdb_backup_incidents', 'jira_ultima_tentativa_em')) {
                $table->timestamp('jira_ultima_tentativa_em')->nullable()->after('jira_criado_em');
            }
            if (! Schema::hasColumn('cmdb_backup_incidents', 'jira_proxima_tentativa_em')) {
                $table->timestamp('jira_proxima_tentativa_em')->nullable()->after('jira_ultima_tentativa_em')->index();
            }
        });

        /*
         | Alvos que já existiam nasceram sem janela auditada. Começar a
         | monitorar a partir de agora evita que a primeira execução acuse
         | atraso de uma janela que nunca foi acompanhada.
         */
        if (Schema::hasTable('cmdb_backup_targets')) {
            \Illuminate\Support\Facades\DB::table('cmdb_backup_targets')
                ->whereNull('monitorar_a_partir_de')
                ->update(['monitorar_a_partir_de' => \Illuminate\Support\Facades\DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        Schema::table('cmdb_backup_incidents', function (Blueprint $table): void {
            foreach (['jira_proxima_tentativa_em', 'jira_ultima_tentativa_em', 'motivo_encerramento', 'encerrado_em'] as $c) {
                if (Schema::hasColumn('cmdb_backup_incidents', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        Schema::table('cmdb_backup_targets', function (Blueprint $table): void {
            foreach (['log_path', 'monitorar_a_partir_de', 'ultima_janela_auditada'] as $c) {
                if (Schema::hasColumn('cmdb_backup_targets', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};

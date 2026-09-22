<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria autônoma de backup.
 *
 * O CoreTI deixa de depender de evento enviado por agente e passa a ser dono
 * do cadastro do que deve existir. Migration incremental: as tabelas criadas
 * antes são preservadas e apenas ganham colunas.
 *
 * Acrescenta:
 *  - no alvo: tipo de backup, método de verificação, periodicidade e janela
 *    esperada, para o agendador decidir sozinho o que auditar e quando;
 *  - no check: duração e erro de conexão, separando "não consegui verificar"
 *    de "verifiquei e está ruim";
 *  - incidentes: um problema aberto por (alvo + tipo de falha), que é o que
 *    impede abrir um chamado novo a cada execução do scheduler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cmdb_backup_targets', function (Blueprint $table): void {
            if (! Schema::hasColumn('cmdb_backup_targets', 'nome')) {
                $table->string('nome')->nullable()->after('id');
            }
            if (! Schema::hasColumn('cmdb_backup_targets', 'tipo_backup')) {
                $table->string('tipo_backup', 40)->default('progress')->after('arquivo')->index();
            }
            if (! Schema::hasColumn('cmdb_backup_targets', 'metodo_verificacao')) {
                $table->string('metodo_verificacao', 40)->default('ssh_arquivo')->after('tipo_backup');
            }
            if (! Schema::hasColumn('cmdb_backup_targets', 'periodicidade')) {
                $table->string('periodicidade', 20)->default('diaria')->after('metodo_verificacao');
            }
            if (! Schema::hasColumn('cmdb_backup_targets', 'horario_esperado')) {
                $table->time('horario_esperado')->nullable()->after('periodicidade');
            }
            if (! Schema::hasColumn('cmdb_backup_targets', 'tolerancia_horas')) {
                $table->unsignedSmallInteger('tolerancia_horas')->default(6)->after('horario_esperado');
            }
            if (! Schema::hasColumn('cmdb_backup_targets', 'estrategia_tamanho')) {
                $table->string('estrategia_tamanho', 20)->default('automatico')->after('min_tamanho_bytes');
            }
        });

        Schema::table('cmdb_backup_checks', function (Blueprint $table): void {
            if (! Schema::hasColumn('cmdb_backup_checks', 'duracao_ms')) {
                $table->unsignedInteger('duracao_ms')->nullable()->after('idade_horas');
            }
            if (! Schema::hasColumn('cmdb_backup_checks', 'erro_conexao')) {
                $table->text('erro_conexao')->nullable()->after('duracao_ms');
            }
            if (! Schema::hasColumn('cmdb_backup_checks', 'incident_id')) {
                $table->unsignedBigInteger('incident_id')->nullable()->after('erro_conexao')->index();
            }
            if (! Schema::hasColumn('cmdb_backup_checks', 'origem')) {
                $table->string('origem', 20)->default('scheduler')->after('incident_id');
            }
        });

        if (! Schema::hasTable('cmdb_backup_incidents')) {
            Schema::create('cmdb_backup_incidents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('target_id')->constrained('cmdb_backup_targets')->cascadeOnDelete();

                // Chave lógica de deduplicação: um incidente aberto por
                // (alvo, tipo de falha). Enquanto ele viver, nada de chamado novo.
                $table->string('tipo_falha', 40)->index();
                $table->string('status', 20)->default('aberto')->index();

                $table->timestamp('aberto_em');
                $table->timestamp('ultima_ocorrencia_em')->nullable();
                $table->timestamp('recuperado_em')->nullable();
                $table->unsignedInteger('ocorrencias')->default(1);

                $table->text('detalhe')->nullable();

                // 'criado' | 'ja_existente' | 'jira_nao_configurado' | 'erro'
                $table->string('jira_status', 30)->nullable();
                $table->string('jira_issue_key', 64)->nullable()->index();
                $table->string('jira_issue_url')->nullable();
                $table->timestamp('jira_criado_em')->nullable();
                $table->text('jira_erro')->nullable();
                $table->boolean('jira_recuperacao_comentada')->default(false);

                $table->timestamps();

                $table->index(['target_id', 'status', 'tipo_falha'], 'cmdb_backup_incidents_dedupe');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_backup_incidents');

        Schema::table('cmdb_backup_checks', function (Blueprint $table): void {
            foreach (['origem', 'incident_id', 'erro_conexao', 'duracao_ms'] as $coluna) {
                if (Schema::hasColumn('cmdb_backup_checks', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });

        Schema::table('cmdb_backup_targets', function (Blueprint $table): void {
            foreach ([
                'estrategia_tamanho', 'tolerancia_horas', 'horario_esperado',
                'periodicidade', 'metodo_verificacao', 'tipo_backup', 'nome',
            ] as $coluna) {
                if (Schema::hasColumn('cmdb_backup_targets', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};

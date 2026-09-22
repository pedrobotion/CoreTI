<?php

namespace App\Console\Commands;

use App\Models\CmdbBackupTarget;
use App\Services\Backups\BackupVerificationService;
use App\Support\Backups\BackupStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Auditoria autônoma de backups.
 *
 * Percorre os alvos CADASTRADOS. Não consulta eventos de webhook, não depende
 * de agente e não descobre nada: se nenhum POST jamais chegar ao CoreTI, este
 * comando continua auditando exatamente os mesmos alvos.
 */
class VerificarBackups extends Command
{
    protected $signature = 'backups:verificar
        {--target= : Verifica apenas este alvo (id)}
        {--host= : Verifica apenas os alvos deste servidor}
        {--todos : Ignora a periodicidade e verifica todos os alvos ativos}
        {--origem=scheduler : Marca a origem no histórico (scheduler|manual)}';

    protected $description = 'Audita os backups cadastrados conectando aos servidores por SSH.';

    public function handle(BackupVerificationService $servico): int
    {
        $query = CmdbBackupTarget::ativos();

        if ($id = $this->option('target')) {
            $query->where('id', (int) $id);
        }

        if ($host = $this->option('host')) {
            $query->where('host', $host);
        }

        $alvos = $query->orderBy('host')->orderBy('database_name')->get();

        if ($alvos->isEmpty()) {
            $this->warn('Nenhum alvo de backup ativo cadastrado.');
            $this->line('Cadastre em CMDB → Backups → Alvos, ou use backups:importar-alvos-legado.');

            return self::SUCCESS;
        }

        // Sem --todos, respeita a periodicidade de cada alvo: um backup diário
        // não precisa de SSH de hora em hora.
        $aVerificar = $this->option('todos') || $this->option('target')
            ? $alvos
            : $alvos->filter(fn (CmdbBackupTarget $t) => $t->deveVerificarAgora())->values();

        $ignorados = $alvos->count() - $aVerificar->count();

        if ($aVerificar->isEmpty()) {
            $this->info("Nenhum alvo devido agora ({$ignorados} dentro da periodicidade). Use --todos para forçar.");

            return self::SUCCESS;
        }

        $this->info("Auditando {$aVerificar->count()} alvo(s)".($ignorados > 0 ? " ({$ignorados} ainda dentro do intervalo)" : '').'...');

        $linhas = [];
        $problemasBackup = 0;
        $problemasAuditoria = 0;
        $pulados = 0;

        foreach ($aVerificar as $alvo) {
            $check = $servico->verificar($alvo, (string) $this->option('origem'));

            if ($check === null) {
                // Outro processo já está auditando este alvo.
                $pulados++;
                continue;
            }

            if (BackupStatus::ehFalhaDeBackup($check->status)) {
                $problemasBackup++;
            } elseif (BackupStatus::ehFalhaDeAuditoria($check->status)) {
                $problemasAuditoria++;
            }

            $linhas[] = [
                $alvo->host,
                $alvo->database_name,
                $alvo->tipoLabel(),
                BackupStatus::rotulo($check->status),
                $check->idadeLegivel(),
                $check->tamanhoLegivel(),
                $check->duracao_ms.'ms',
            ];
        }

        if ($linhas !== []) {
            $this->table(
                ['Servidor', 'Banco', 'Tipo', 'Status', 'Idade', 'Tamanho', 'Duração'],
                $linhas
            );
        }

        if ($pulados > 0) {
            $this->comment("{$pulados} alvo(s) pulado(s): já havia verificação em andamento.");
        }

        $this->newLine();
        $this->info(sprintf(
            '%d verificado(s) | %d falha(s) de backup | %d falha(s) de auditoria.',
            count($linhas),
            $problemasBackup,
            $problemasAuditoria
        ));

        if ($problemasAuditoria > 0) {
            $this->warn('Falha de auditoria não afirma nada sobre o backup: o CoreTI não conseguiu verificar.');
        }

        if ($problemasBackup > 0 || $problemasAuditoria > 0) {
            Log::warning('backup.auditoria', [
                'verificados' => count($linhas),
                'falhas_backup' => $problemasBackup,
                'falhas_auditoria' => $problemasAuditoria,
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

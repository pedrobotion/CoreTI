<?php

namespace App\Services\Backups;

use App\Models\CmdbBackupTarget;
use App\Models\CmdbDatabaseBackup;
use Illuminate\Support\Collection;

/**
 * Importador ÚNICO E MANUAL de alvos a partir do histórico legado.
 *
 * ATENÇÃO: este serviço NÃO faz parte do fluxo de auditoria.
 *
 * A auditoria autônoma parte de CmdbBackupTarget, que é a fonte da verdade.
 * Esta classe existe apenas como conveniência de migração: quem já tinha
 * eventos vindos do agente/n8n pode semear o cadastro inicial em vez de
 * digitar tudo, uma vez, pelo comando backups:importar-alvos-legado.
 *
 * Nunca chame isto a partir do scheduler.
 *
 * @see \App\Console\Commands\VerificarBackups fluxo real de auditoria
 */
class BackupTargetDiscoveryService
{
    /**
     * @return Collection<int, CmdbBackupTarget> alvos criados nesta execução
     */
    public function importarDoHistoricoLegado(): Collection
    {
        $candidatos = CmdbDatabaseBackup::query()
            ->select('host', 'database_name', 'arquivo', 'ip_address', 'asset_id')
            ->whereNotNull('arquivo')
            ->where('arquivo', '!=', '')
            ->sucessos()
            ->orderByDesc('data_backup')
            ->get()
            ->unique(fn (CmdbDatabaseBackup $b) => $b->host.'|'.$b->database_name.'|'.$b->arquivo);

        $criados = collect();

        foreach ($candidatos as $candidato) {
            $existente = CmdbBackupTarget::where('host', $candidato->host)
                ->where('database_name', $candidato->database_name)
                ->where('arquivo', $candidato->arquivo)
                ->first();

            if ($existente !== null) {
                // Mantém o vínculo com o ativo em dia, sem tocar no que foi
                // ajustado à mão.
                if ($existente->asset_id === null && $candidato->asset_id !== null) {
                    $existente->update(['asset_id' => $candidato->asset_id]);
                }

                continue;
            }

            $criados->push(CmdbBackupTarget::create([
                'host' => $candidato->host,
                'database_name' => $candidato->database_name,
                'arquivo' => $candidato->arquivo,
                // O IP reportado costuma resolver mesmo quando o hostname curto
                // não resolve no DNS do servidor do CoreTI.
                'ssh_host' => $candidato->ip_address,
                'asset_id' => $candidato->asset_id,
                'origem_cadastro' => 'importado_legado',
                'tipo_backup' => 'progress',
                'metodo_verificacao' => 'progress_arquivo',
                'periodicidade' => 'diaria',
                'estrategia_tamanho' => 'automatico',
                // Importado fica INATIVO de propósito: alguém precisa conferir
                // host SSH, usuário e limites antes de o CoreTI sair conectando.
                'ativo' => false,
            ]));
        }

        return $criados;
    }
}

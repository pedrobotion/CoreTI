<?php

namespace App\Services\Backups;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupIncident;
use App\Models\CmdbBackupTarget;
use App\Support\Backups\BackupStatus;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de um problema de backup.
 *
 * Regra central: enquanto houver incidente ABERTO para (alvo, tipo de falha),
 * novas ocorrências apenas incrementam o contador — não geram chamado novo.
 * Quando o alvo volta a OK, o incidente é marcado como recuperado, o que
 * permite distinguir problema novo, recorrente e resolvido.
 */
class BackupIncidentService
{
    public function __construct(private readonly DatabaseBackupJiraService $jira)
    {
    }

    /**
     * Registra a observação e devolve o incidente correspondente (ou null se
     * o alvo está saudável).
     */
    public function registrar(CmdbBackupTarget $target, CmdbBackupCheck $check): ?CmdbBackupIncident
    {
        if (! BackupStatus::ehProblema($check->status)) {
            $this->registrarRecuperacao($target, $check);

            return null;
        }

        $incidente = DB::transaction(function () use ($target, $check) {
            $aberto = CmdbBackupIncident::abertos()
                ->where('target_id', $target->id)
                ->where('tipo_falha', $check->status)
                ->lockForUpdate()
                ->first();

            if ($aberto !== null) {
                $aberto->increment('ocorrencias');
                $aberto->update([
                    'ultima_ocorrencia_em' => $check->executado_em,
                    'detalhe' => $check->detalhe ?: $aberto->detalhe,
                ]);

                return $aberto->fresh();
            }

            /*
             | Falha de tipo diferente no mesmo alvo: o problema NÃO acabou,
             | mudou de natureza (ex.: de "atrasado" para "ausente").
             |
             | Encerrado como SUBSTITUÍDO, com recuperado_em intocado: contar
             | isso como recuperação inflaria o SLA e faria o MTTR mentir.
             */
            CmdbBackupIncident::abertos()
                ->where('target_id', $target->id)
                ->update([
                    'status' => CmdbBackupIncident::STATUS_SUBSTITUIDO,
                    'encerrado_em' => now(),
                    'motivo_encerramento' => CmdbBackupIncident::MOTIVO_TIPO_ALTERADO,
                ]);

            return CmdbBackupIncident::create([
                'target_id' => $target->id,
                'tipo_falha' => $check->status,
                'status' => CmdbBackupIncident::STATUS_ABERTO,
                'aberto_em' => $check->executado_em,
                'ultima_ocorrencia_em' => $check->executado_em,
                'ocorrencias' => 1,
                'detalhe' => $check->detalhe,
            ]);
        });

        $check->update(['incident_id' => $incidente->id]);

        $this->garantirChamado($target, $incidente, $check);

        return $incidente->fresh();
    }

    /**
     * Abre o chamado apenas na primeira ocorrência do incidente. Reexecuções
     * do scheduler encontram jira_status já preenchido e não repetem.
     */
    private function garantirChamado(
        CmdbBackupTarget $target,
        CmdbBackupIncident $incidente,
        CmdbBackupCheck $check,
    ): void {
        if ($incidente->jira_issue_key !== null) {
            $check->update([
                'jira_issue_key' => $incidente->jira_issue_key,
                'jira_issue_url' => $incidente->jira_issue_url,
            ]);

            return;
        }

        /*
         | Cooldown temporal.
         |
         | Antes a janela era inferida do valor textual de jira_status: a
         | primeira reexecução trocava 'erro' por 'cooldown' e, na seguinte, a
         | condição de erro já não casava — o Jira era tentado de novo bem
         | antes dos minutos configurados. Agora quem manda é a data da
         | próxima tentativa permitida.
         */
        if (! $incidente->podeTentarJira()) {
            if ($incidente->jira_status !== CmdbBackupIncident::JIRA_EM_COOLDOWN) {
                $incidente->update(['jira_status' => CmdbBackupIncident::JIRA_EM_COOLDOWN]);
            }

            return;
        }

        $resultado = $this->jira->abrirParaIncidente($target, $incidente, $check);
        $agora = now();

        $atualizacao = [
            'jira_status' => $resultado['status'],
            'jira_issue_key' => $resultado['issue_key'],
            'jira_issue_url' => $resultado['issue_url'],
            'jira_ultima_tentativa_em' => $agora,
            'jira_erro' => $resultado['message'] ?? null,
        ];

        if ($resultado['status'] === CmdbBackupIncident::JIRA_ERRO) {
            $atualizacao['jira_proxima_tentativa_em'] = $agora->copy()
                ->addMinutes((int) config('backups.jira_cooldown_minutes', 360));
        } else {
            // Sucesso ou Jira não configurado: nada a segurar. O segundo caso
            // não faz requisição alguma, então reavaliar de graça é preferível
            // a esperar horas depois de configurarem as credenciais.
            $atualizacao['jira_proxima_tentativa_em'] = null;
        }

        if ($resultado['issue_key']) {
            $atualizacao['jira_criado_em'] = $agora;
        }

        $incidente->update($atualizacao);

        if ($resultado['issue_key']) {
            $check->update([
                'jira_issue_key' => $resultado['issue_key'],
                'jira_issue_url' => $resultado['issue_url'],
                'jira_dispatched_at' => now(),
            ]);
        }
    }

    /**
     * Alvo voltou a ficar saudável: fecha os incidentes abertos e, se houver
     * chamado, registra a normalização.
     */
    private function registrarRecuperacao(CmdbBackupTarget $target, CmdbBackupCheck $check): void
    {
        $abertos = CmdbBackupIncident::abertos()->where('target_id', $target->id)->get();

        foreach ($abertos as $incidente) {
            $incidente->update([
                'status' => CmdbBackupIncident::STATUS_RECUPERADO,
                'recuperado_em' => $check->executado_em,
                'encerrado_em' => $check->executado_em,
                'motivo_encerramento' => CmdbBackupIncident::MOTIVO_RECUPERACAO,
            ]);

            if ($incidente->jira_issue_key && ! $incidente->jira_recuperacao_comentada) {
                $comentado = $this->jira->comentarRecuperacao($target, $incidente->fresh(), $check);

                if ($comentado) {
                    $incidente->update(['jira_recuperacao_comentada' => true]);
                }
            }
        }
    }
}

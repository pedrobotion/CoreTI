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
     * Por que este incidente ainda NÃO deve virar chamado.
     *
     * Devolve null quando pode abrir. Os quatro critérios de
     * config('backups.abertura') se somam — a mensagem diz qual barrou, e ela
     * aparece no painel, para ninguém achar que o chamado falhou.
     */
    private function faltaConfirmar(CmdbBackupTarget $target, CmdbBackupIncident $incidente): ?string
    {
        $regras = (array) config('backups.abertura', []);

        // 1. Falha de auditoria é suspeita: o CoreTI não conseguiu verificar,
        //    e isso não afirma nada sobre o backup.
        $tipos = (array) ($regras['tipos'] ?? ['backup']);
        $ehDeBackup = in_array($incidente->tipo_falha, BackupStatus::FALHAS_DE_BACKUP, true);
        $classe = $ehDeBackup ? 'backup' : 'auditoria';

        if (! in_array($classe, $tipos, true)) {
            return $ehDeBackup
                ? 'Tipo de falha fora da abertura automática.'
                : 'Falha de auditoria não abre chamado: o CoreTI não conseguiu verificar, '
                  .'o que não afirma que o backup falhou. Trate o acesso ao servidor.';
        }

        // 2. Uma falha é suspeita; repetição é problema. Também protege contra
        //    perda de estado: incidente recriado volta a ocorrencias = 1.
        $minimo = (int) ($regras['minimo_ocorrencias'] ?? 3);

        if ($minimo > 0 && (int) $incidente->ocorrencias < $minimo) {
            return sprintf(
                'Aguardando confirmação: %d de %d verificações com falha.',
                (int) $incidente->ocorrencias,
                $minimo
            );
        }

        // 3. Três verificações em dois minutos não são confirmação, são a
        //    mesma leitura repetida. O problema precisa atravessar o tempo.
        $minutos = (int) ($regras['minimo_minutos'] ?? 120);

        if ($minutos > 0 && $incidente->aberto_em !== null
            && $incidente->aberto_em->greaterThan(now()->subMinutes($minutos))) {
            return sprintf('Aguardando confirmação: problema aberto há menos de %d minutos.', $minutos);
        }

        // 4. Alvo que nunca funcionou não tem problema de backup: tem cadastro
        //    errado, e isso é tarefa de configuração, não chamado.
        if (($regras['exigir_sucesso_anterior'] ?? true) && ! $this->jaTeveSucesso($target)) {
            return 'Este alvo nunca teve um backup verificado com sucesso — '
                .'trate o cadastro antes de tratar como falha de backup.';
        }

        return null;
    }

    /** O alvo já produziu ao menos uma verificação bem-sucedida? */
    private function jaTeveSucesso(CmdbBackupTarget $target): bool
    {
        return CmdbBackupCheck::query()
            ->where('target_id', $target->id)
            ->where('status', BackupStatus::OK)
            ->exists();
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
         | Confirmação antes de interromper alguém.
         |
         | Chamado é interrupção do trabalho de uma pessoa. Abrir na primeira
         | suspeita gasta atenção com o que se resolve sozinho — e quem recebe
         | alarme demais para de ler alarme, aí o problema real passa junto.
         |
         | O incidente continua registrado e visível no painel enquanto não
         | confirma; o que espera é só o chamado.
         */
        if (($motivo = $this->faltaConfirmar($target, $incidente)) !== null) {
            $incidente->update([
                'jira_status' => CmdbBackupIncident::JIRA_AGUARDANDO_CONFIRMACAO,
                'jira_erro' => $motivo,
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

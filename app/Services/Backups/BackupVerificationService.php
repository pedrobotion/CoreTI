<?php

namespace App\Services\Backups;

use App\Models\CmdbBackupCheck;
use App\Models\CmdbBackupTarget;
use App\Services\Backups\Verification\StrategyResolver;
use App\Services\Backups\Verification\VerificationResult;
use App\Support\Backups\BackupStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Orquestra a auditoria de um alvo.
 *
 * Fluxo: alvo -> estratégia -> observação -> avaliação -> check -> incidente.
 *
 * A estratégia relata o que viu; as regras de saúde (idade máxima, tamanho
 * mínimo) ficam aqui, para que uma tecnologia nova herde as mesmas regras sem
 * reimplementá-las.
 */
class BackupVerificationService
{
    public function __construct(
        private readonly StrategyResolver $resolver,
        private readonly BackupIncidentService $incidentes,
    ) {
    }

    /**
     * Verifica um alvo. O lock impede que o scheduler e um "Verificar agora"
     * disparado na tela auditem o mesmo alvo ao mesmo tempo.
     */
    public function verificar(CmdbBackupTarget $target, string $origem = 'scheduler'): ?CmdbBackupCheck
    {
        $lock = Cache::lock('backup-target-'.$target->id, 120);

        if (! $lock->get()) {
            return null;
        }

        try {
            return $this->executar($target, $origem);
        } finally {
            $lock->release();
        }
    }

    private function executar(CmdbBackupTarget $target, string $origem): CmdbBackupCheck
    {
        $resultado = $this->resolver->para($target)->verificar($target);

        /*
         | Relógios podem divergir entre o CoreTI e o servidor auditado. Se o
         | mtime vier adiantado, a idade fica negativa — e idade_horas é
         | unsigned, o que derrubava a verificação inteira com erro de SQL.
         | Idade é limitada a zero e a divergência vira observação.
         */
        $idadeBruta = $resultado->modificadoEm !== null
            ? (int) $resultado->modificadoEm->diffInHours(now())
            : null;

        $relogioAdiantado = $idadeBruta !== null && $idadeBruta < 0;
        $idadeHoras = $idadeBruta === null ? null : max(0, $idadeBruta);

        $avaliacao = $this->avaliar($target, $resultado, $idadeHoras);

        if ($relogioAdiantado) {
            $avaliacao['detalhe'] = trim(sprintf(
                '%s Atenção: o arquivo tem data %s, à frente do relógio do CoreTI '
                .'(diferença de %dh). Verifique a sincronização de horário do servidor.',
                (string) $avaliacao['detalhe'],
                $resultado->modificadoEm->format('d/m/Y H:i'),
                abs($idadeBruta)
            ));
        }

        $check = CmdbBackupCheck::create([
            'target_id' => $target->id,
            'executado_em' => now(),
            'status' => $avaliacao['status'],
            'detalhe' => $avaliacao['detalhe'],
            'arquivo_existe' => $resultado->arquivoExiste,
            'arquivo_tamanho_bytes' => $resultado->tamanhoBytes,
            'arquivo_modificado_em' => $resultado->modificadoEm,
            'idade_horas' => $idadeHoras,
            'duracao_ms' => $resultado->duracaoMs,
            'erro_conexao' => $resultado->erroConexao,
            'origem' => $origem,
        ]);

        $atualizacao = [
            'last_checked_at' => $check->executado_em,
            'last_status' => $check->status,
        ];

        /*
         | Só execução do scheduler fecha a janela. Uma verificação manual às
         | 16h não pode empurrar a auditoria automática do dia seguinte —
         | por isso ela atualiza last_checked_at, mas não ultima_janela_auditada.
         */
        if ($origem === 'scheduler') {
            $fimDaJanela = $target->fimDaJanelaMaisRecente($check->executado_em);
            $atualizacao['ultima_janela_auditada'] = $fimDaJanela ?? $check->executado_em;
        }

        $target->update($atualizacao);

        $this->incidentes->registrar($target, $check);

        return $check->fresh();
    }

    /**
     * Traduz a observação em estado de saúde.
     *
     * Ordem deliberada: falha de auditoria primeiro (não sabemos nada sobre o
     * backup), depois ausência, depois idade, depois tamanho. Só faz sentido
     * questionar o tamanho de um arquivo que existe e está no prazo.
     *
     * @return array{status: string, detalhe: ?string}
     */
    private function avaliar(CmdbBackupTarget $target, VerificationResult $resultado, ?int $idadeHoras): array
    {
        if (BackupStatus::ehFalhaDeAuditoria($resultado->status)) {
            return ['status' => $resultado->status, 'detalhe' => $resultado->detalhe];
        }

        if ($resultado->status === BackupStatus::ARQUIVO_AUSENTE || ! $resultado->arquivoExiste) {
            return [
                'status' => BackupStatus::ARQUIVO_AUSENTE,
                'detalhe' => $resultado->detalhe ?: sprintf(
                    'Arquivo %s não encontrado em %s.',
                    $target->arquivo,
                    $target->enderecoSsh()
                ),
            ];
        }

        $idadeMaxima = $target->idadeMaximaHoras();

        if ($idadeHoras !== null && $idadeHoras > $idadeMaxima) {
            return [
                'status' => BackupStatus::ATRASADO,
                'detalhe' => sprintf(
                    'Último backup tem %dh, acima do limite de %dh definido para este alvo.',
                    $idadeHoras,
                    $idadeMaxima
                ),
            ];
        }

        $minimo = $target->tamanhoMinimoBytes();

        if ($minimo !== null && $resultado->tamanhoBytes !== null && $resultado->tamanhoBytes < $minimo) {
            return [
                'status' => BackupStatus::TAMANHO_ANORMAL,
                'detalhe' => sprintf(
                    'Arquivo com %s, abaixo do esperado (%s, referência %s). '
                    .'Isso indica tamanho fora do padrão, não necessariamente corrupção.',
                    $this->humano($resultado->tamanhoBytes),
                    $this->humano($minimo),
                    $target->origemTamanhoMinimo()
                ),
            ];
        }

        return [
            'status' => BackupStatus::OK,
            'detalhe' => sprintf(
                'Arquivo encontrado: %s, modificado há %s.',
                $this->humano($resultado->tamanhoBytes ?? 0),
                $idadeHoras !== null ? $idadeHoras.' hora(s)' : 'data desconhecida'
            ),
        ];
    }

    private function humano(int $bytes): string
    {
        foreach ([['GB', 1024 ** 3], ['MB', 1024 ** 2], ['KB', 1024]] as [$unidade, $divisor]) {
            if ($bytes >= $divisor) {
                return number_format($bytes / $divisor, 2, ',', '.').' '.$unidade;
            }
        }

        return $bytes.' B';
    }
}

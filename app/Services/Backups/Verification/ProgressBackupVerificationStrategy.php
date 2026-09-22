<?php

namespace App\Services\Backups\Verification;

use App\Models\CmdbBackupTarget;

/**
 * Progress / OpenEdge.
 *
 * A auditoria do artefato (.onl gerado pelo probkup) é herdada e funciona
 * normalmente — existência, idade e tamanho.
 *
 * Sobre a análise do log (.lg): o alvo tem um campo opcional log_path. Ele
 * está preparado e o wrapper remoto já expõe a operação 'progress-log', mas a
 * INTERPRETAÇÃO do conteúdo não foi implementada de propósito.
 *
 * Motivo: identificar início, conclusão e falha do probkup exige conhecer as
 * mensagens exatas que esta instalação emite (variam com versão, locale e
 * parâmetros). Não há, neste projeto nem neste ambiente, evidência de quais
 * são — e adivinhar as strings produziria uma auditoria que reporta sucesso
 * ou falha sem base real, que é pior que não auditar.
 *
 * Para implementar: colete algumas linhas de backup do .lg de uma base real,
 * confirme os marcadores e só então escreva o parser.
 */
class ProgressBackupVerificationStrategy extends SshFileVerificationStrategy
{
    public function chave(): string
    {
        return 'progress_arquivo';
    }

    public function rotulo(): string
    {
        return 'Progress (arquivo .onl via SSH)';
    }

    public function tiposSuportados(): array
    {
        return ['progress'];
    }

    public function verificar(CmdbBackupTarget $target): VerificationResult
    {
        $resultado = parent::verificar($target);

        $resultado->evidencias['tecnologia'] = 'progress';
        $resultado->evidencias['log_path'] = $target->log_path ?: null;
        $resultado->evidencias['analise_log'] = $target->log_path
            ? 'log configurado; análise do log Progress ainda não implementada'
            : 'sem log configurado; auditoria pelo arquivo .onl';

        return $resultado;
    }
}

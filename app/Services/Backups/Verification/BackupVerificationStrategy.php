<?php

namespace App\Services\Backups\Verification;

use App\Models\CmdbBackupTarget;

/**
 * Como inspecionar um backup de determinada tecnologia.
 *
 * Toda implementação é obrigatoriamente somente leitura: auditoria nunca
 * escreve, move ou remove nada no servidor auditado.
 */
interface BackupVerificationStrategy
{
    /**
     * Identificador usado no cadastro do alvo (coluna metodo_verificacao).
     */
    public function chave(): string;

    public function rotulo(): string;

    /**
     * Tipos de backup que esta estratégia atende ('progress', 'mysql', ...).
     *
     * @return array<int, string>
     */
    public function tiposSuportados(): array;

    public function verificar(CmdbBackupTarget $target): VerificationResult;
}

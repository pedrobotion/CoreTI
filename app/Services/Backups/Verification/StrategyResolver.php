<?php

namespace App\Services\Backups\Verification;

use App\Models\CmdbBackupTarget;

/**
 * Escolhe a estratégia do alvo.
 *
 * Registrar uma tecnologia nova é acrescentar uma implementação aqui — o
 * serviço de verificação e o comando agendado não mudam.
 */
class StrategyResolver
{
    /** @var array<int, BackupVerificationStrategy> */
    private array $estrategias;

    public function __construct(
        SshFileVerificationStrategy $arquivo,
        ProgressBackupVerificationStrategy $progress,
    ) {
        // Ordem importa: a primeira que atender a chave do alvo vence.
        $this->estrategias = [$progress, $arquivo];
    }

    public function para(CmdbBackupTarget $target): BackupVerificationStrategy
    {
        foreach ($this->estrategias as $estrategia) {
            if ($estrategia->chave() === $target->metodo_verificacao) {
                return $estrategia;
            }
        }

        // Método desconhecido ou não informado cai na inspeção genérica de
        // arquivo, que atende todos os tipos cadastrados hoje.
        return $this->estrategias[array_key_last($this->estrategias)];
    }

    /**
     * @return array<string, string> chave => rótulo, para o formulário
     */
    public function opcoes(): array
    {
        $opcoes = [];

        foreach ($this->estrategias as $estrategia) {
            $opcoes[$estrategia->chave()] = $estrategia->rotulo();
        }

        return $opcoes;
    }
}

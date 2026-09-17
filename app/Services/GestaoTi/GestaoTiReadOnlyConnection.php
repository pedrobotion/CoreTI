<?php

namespace App\Services\GestaoTi;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Acesso somente leitura à base do gestaoTI.
 *
 * O gestaoTI continua em produção e é dono desses dados. A garantia de que a
 * importação não escreve nele não pode depender de quem programa lembrar de
 * só usar SELECT: aqui o interceptador do próprio Laravel recusa qualquer
 * comando que não seja de leitura, antes de ele chegar ao banco.
 *
 * A credencial configurada tem permissão de escrita no MySQL de origem — este
 * guarda é justamente o que torna essa permissão inalcançável pela aplicação.
 * Trocar a credencial por um usuário read-only no MySQL continua sendo a
 * melhoria recomendada; enquanto isso não acontece, o bloqueio é este.
 */
class GestaoTiReadOnlyConnection
{
    private static bool $guardaRegistrada = false;

    /** Comandos aceitos: leitura pura. */
    private const PERMITIDOS = '/^\s*(select|show|describe|desc|explain)\b/i';

    public static function get(): Connection
    {
        /** @var Connection $conexao */
        $conexao = DB::connection('gestaoti');

        if (! self::$guardaRegistrada) {
            $conexao->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
                if (preg_match(self::PERMITIDOS, $query) !== 1) {
                    throw new RuntimeException(
                        'A conexão "gestaoti" é somente leitura e recusou este comando: '
                        .mb_substr(trim($query), 0, 160)
                    );
                }
            });

            self::$guardaRegistrada = true;
        }

        return $conexao;
    }

    /**
     * Confere se a origem responde, sem escrever nada.
     *
     * @return array{ok: bool, mensagem: string}
     */
    public static function verificar(): array
    {
        try {
            $conexao = self::get();
            $banco = $conexao->getDatabaseName();
            $tabelas = ['linhas', 'celulares', 'equipamentos', 'vinculos', 'vinculo_notebooks', 'colaboradores_intranet', 'tenants'];

            foreach ($tabelas as $tabela) {
                $conexao->table($tabela)->limit(1)->count();
            }

            return ['ok' => true, 'mensagem' => "Conectado em {$banco} (somente leitura)."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensagem' => $e->getMessage()];
        }
    }

    /** Usado apenas por testes que precisam reinstalar o guarda. */
    public static function esquecerGuarda(): void
    {
        self::$guardaRegistrada = false;
    }
}

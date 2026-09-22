<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Barreira contra comandos que a aplicação web nunca precisa emitir.
 *
 * Existe para que uma injeção de SQL que chegue à conexão do Laravel não
 * consiga derrubar o servidor, apagar bancos ou mexer em contas — a conta que
 * a aplicação usa tem privilégio para tudo isso, então a barreira é aqui.
 *
 * Só atua em requisição HTTP. No console o guarda sai da frente, porque
 * migration e seed legitimamente emitem DDL.
 *
 * Escape hatch: DB_GUARDA_BLOQUEAR=false volta ao modo observação (registra
 * sem recusar) e DB_GUARDA_ATIVO=false desliga. Se um falso positivo quebrar
 * uma tela, é uma linha no .env e um config:clear para destravar.
 */
class GuardaDeComandosPerigosos
{
    /**
     * Comandos que nenhuma tela emite.
     *
     * O prefixo (?:^|;) casa tanto o início da consulta quanto um comando
     * encadeado depois de ponto e vírgula — 'select 1; drop database x' é a
     * forma clássica de injeção e não pode escapar por estar no fim.
     *
     * TEMPORARY fica de fora dos padrões de CREATE/DROP porque tabela
     * temporária é recurso legítimo de consulta pesada.
     */
    private const PROIBIDOS = [
        // Destruição de bancos inteiros
        '/(?:^|;)\s*drop\s+(database|schema)\b/i',

        // Contas e privilégios
        '/(?:^|;)\s*(create|alter|drop|rename)\s+user\b/i',
        '/(?:^|;)\s*(grant|revoke)\b/i',

        // Controle do servidor
        '/(?:^|;)\s*shutdown\b/i',
        '/(?:^|;)\s*(flush|kill)\b/i',
        '/(?:^|;)\s*set\s+(global|persist)\b/i',
        '/(?:^|;)\s*(create|drop)\s+tablespace\b/i',

        // Leitura e escrita de arquivos no sistema de arquivos do servidor
        '/(?:^|;)\s*load\s+data\b/i',
        '/\binto\s+(outfile|dumpfile)\b/i',

        // DDL: migration roda no console, onde este guarda não atua.
        // Numa requisição HTTP, DDL é sempre injeção.
        '/(?:^|;)\s*drop\s+(?!temporary\b)(table|index|view|trigger|procedure|function|event)\b/i',
        '/(?:^|;)\s*create\s+(?!temporary\b)(table|index|view|trigger|procedure|function|event)\b/i',
        '/(?:^|;)\s*(alter|rename)\s+(table|view|procedure|function|event)\b/i',
        '/(?:^|;)\s*truncate\b/i',
    ];

    public static function registrar(): void
    {
        // Migration, seed e comandos de sincronização precisam de DDL.
        if (app()->runningInConsole()) {
            return;
        }

        if (! (bool) config('database.guarda_comandos_perigosos.ativo', true)) {
            return;
        }

        // Conexões já resolvidas, e todas as que vierem depois. A aplicação
        // usa várias (intranet_infra, gestaoTI, chamados); proteger só a
        // padrão deixaria as outras abertas.
        foreach (DB::getConnections() as $conexao) {
            self::proteger($conexao);
        }

        Event::listen(
            ConnectionEstablished::class,
            static fn (ConnectionEstablished $evento) => self::proteger($evento->connection)
        );
    }

    private static function proteger(Connection $conexao): void
    {
        $bloquear = (bool) config('database.guarda_comandos_perigosos.bloquear', true);

        $conexao->beforeExecuting(
            function (string $query, array $bindings, Connection $conexao) use ($bloquear): void {
                if (self::detecta($query) === null) {
                    return;
                }

                $resumo = mb_substr(self::normalizar($query), 0, 200);

                Log::critical('banco.comando_perigoso_em_requisicao', [
                    'bloqueado' => $bloquear,
                    'conexao' => $conexao->getName(),
                    'comando' => $resumo,
                    'rota' => optional(request()->route())->getName(),
                    'url' => request()->fullUrl(),
                    'ip' => request()->ip(),
                    'user_id' => auth()->id(),
                ]);

                if ($bloquear) {
                    throw new ComandoPerigosoRecusado($resumo);
                }
            }
        );
    }

    /**
     * O comando é proibido? Devolve o padrão que casou, ou null.
     *
     * Público e sem dependência de banco de propósito: é assim que a detecção
     * se verifica — passando strings — sem nunca executar nada.
     */
    public static function detecta(string $sql): ?string
    {
        $normalizado = self::normalizar($sql);

        foreach (self::PROIBIDOS as $padrao) {
            if (preg_match($padrao, $normalizado) === 1) {
                return $padrao;
            }
        }

        return null;
    }

    /**
     * Tira comentários e normaliza espaços.
     *
     * Sem isso, 'DROP/*x*(/)DATABASE alvo' passaria: o comentário quebra o
     * casamento do padrão. Remover comentários só aproxima tokens separados —
     * nunca cria uma palavra-chave que não estivesse escrita.
     */
    private static function normalizar(string $sql): string
    {
        $limpo = preg_replace('#/\*.*?\*/#s', ' ', $sql) ?? $sql;
        $limpo = preg_replace('/--[^\r\n]*/', ' ', $limpo) ?? $limpo;
        $limpo = preg_replace('/#[^\r\n]*/', ' ', $limpo) ?? $limpo;

        return trim(preg_replace('/\s+/', ' ', $limpo) ?? $limpo);
    }
}

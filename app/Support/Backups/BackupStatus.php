<?php

namespace App\Support\Backups;

/**
 * Estados de uma verificação de backup.
 *
 * Os valores internos foram mantidos curtos e compatíveis com o que já estava
 * gravado ('ok', 'velho', 'ausente', 'pequeno', 'inacessivel'). A interface usa
 * os rótulos em português.
 *
 * Distinção deliberada: servidor inacessível e chave recusada NÃO são "backup
 * falhou" — são falhas de auditoria. Confundir as duas coisas gera chamado
 * errado para a equipe errada.
 */
final class BackupStatus
{
    public const OK = 'ok';
    public const ATRASADO = 'velho';
    public const ARQUIVO_AUSENTE = 'ausente';
    public const TAMANHO_ANORMAL = 'pequeno';
    public const SERVIDOR_INACESSIVEL = 'inacessivel';
    public const ERRO_AUTENTICACAO = 'auth_negada';
    public const ERRO_VERIFICACAO = 'erro';
    public const NUNCA_VERIFICADO = null;

    /**
     * Problemas do backup em si — geram chamado para quem cuida do backup.
     */
    public const FALHAS_DE_BACKUP = [
        self::ATRASADO,
        self::ARQUIVO_AUSENTE,
        self::TAMANHO_ANORMAL,
    ];

    /**
     * Problemas da auditoria — o CoreTI não conseguiu verificar. Não afirmam
     * nada sobre o backup.
     */
    public const FALHAS_DE_AUDITORIA = [
        self::SERVIDOR_INACESSIVEL,
        self::ERRO_AUTENTICACAO,
        self::ERRO_VERIFICACAO,
    ];

    public static function todos(): array
    {
        return array_merge([self::OK], self::FALHAS_DE_BACKUP, self::FALHAS_DE_AUDITORIA);
    }

    public static function rotulo(?string $status): string
    {
        return match ($status) {
            self::OK => 'OK',
            self::ATRASADO => 'Atrasado',
            self::ARQUIVO_AUSENTE => 'Arquivo ausente',
            self::TAMANHO_ANORMAL => 'Tamanho anormal',
            self::SERVIDOR_INACESSIVEL => 'Servidor inacessível',
            self::ERRO_AUTENTICACAO => 'Erro de autenticação',
            self::ERRO_VERIFICACAO => 'Erro de verificação',
            default => 'Nunca verificado',
        };
    }

    public static function descricao(?string $status): string
    {
        return match ($status) {
            self::OK => 'Arquivo presente, dentro do prazo e com tamanho plausível.',
            self::ATRASADO => 'O arquivo existe, mas é mais antigo que a idade máxima permitida.',
            self::ARQUIVO_AUSENTE => 'O arquivo esperado não foi encontrado no servidor.',
            self::TAMANHO_ANORMAL => 'O arquivo está abaixo do tamanho esperado para este backup.',
            self::SERVIDOR_INACESSIVEL => 'Não foi possível alcançar o servidor. Nada se pode afirmar sobre o backup.',
            self::ERRO_AUTENTICACAO => 'O servidor respondeu, mas recusou a credencial do CoreTI.',
            self::ERRO_VERIFICACAO => 'A verificação falhou por um motivo inesperado.',
            default => 'Este alvo ainda não foi verificado nenhuma vez.',
        };
    }

    /**
     * Cor semântica para a interface.
     */
    public static function tom(?string $status): string
    {
        if ($status === self::OK) {
            return 'emerald';
        }

        if (in_array($status, self::FALHAS_DE_BACKUP, true)) {
            return 'rose';
        }

        if (in_array($status, self::FALHAS_DE_AUDITORIA, true)) {
            return 'amber';
        }

        return 'slate';
    }

    /**
     * Classes completas para o Tailwind.
     *
     * O JIT do Tailwind varre o código por strings de classe inteiras — nomes
     * montados por interpolação ("bg-{$tom}-100") não são gerados e a cor
     * simplesmente não aparece. Por isso as combinações ficam literais aqui.
     */
    public static function classesBadge(?string $status): string
    {
        return match (self::tom($status)) {
            'emerald' => 'bg-emerald-100 text-emerald-800',
            'rose' => 'bg-rose-100 text-rose-800',
            'amber' => 'bg-amber-100 text-amber-800',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public static function classesCartao(string $tom): string
    {
        return match ($tom) {
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'rose' => 'border-rose-200 bg-rose-50 text-rose-700',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-slate-200 bg-white text-slate-500',
        };
    }

    public static function classesNumero(string $tom): string
    {
        return match ($tom) {
            'emerald' => 'text-emerald-900',
            'rose' => 'text-rose-900',
            'amber' => 'text-amber-900',
            default => 'text-slate-950',
        };
    }

    public static function ehFalhaDeBackup(?string $status): bool
    {
        return in_array($status, self::FALHAS_DE_BACKUP, true);
    }

    public static function ehFalhaDeAuditoria(?string $status): bool
    {
        return in_array($status, self::FALHAS_DE_AUDITORIA, true);
    }

    public static function ehProblema(?string $status): bool
    {
        return $status !== null && $status !== self::OK;
    }
}

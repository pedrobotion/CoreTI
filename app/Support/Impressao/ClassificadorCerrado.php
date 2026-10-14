<?php

namespace App\Support\Impressao;

/**
 * Reconhece impressoras do Cerrado por sinais do dispositivo (sigla no hostname
 * ou sub-rede), a partir do mapa em config/insight.php → 'cerrado'.
 *
 * Fonte única usada tanto pela sincronização (para vincular a unidade e, com
 * ela, o escopo) quanto pelo roteamento do e-mail de alertas. Devolve o
 * source_id da unidade e a cidade; nunca "chuta" Paraná — ausência de sinal é
 * null, e quem chama decide o padrão.
 */
class ClassificadorCerrado
{
    /**
     * @return array{source_id:int,cidade:string}|null
     */
    public static function fonte(?string $hostname, ?string $ip): ?array
    {
        $cfg = (array) config('insight.cerrado', []);

        // 1) Sigla da cidade no hostname (ex.: PIR-LASER-ADM-1).
        $prefixos = (array) ($cfg['prefixos'] ?? []);
        $tokens = preg_split('/[^A-Z0-9]+/', strtoupper((string) $hostname), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($tokens as $t) {
            if (isset($prefixos[$t])) {
                return ['source_id' => (int) $prefixos[$t][0], 'cidade' => (string) $prefixos[$t][1]];
            }
        }

        // 2) Sub-rede do bloco do Cerrado (pega quem não tem sigla).
        $ip = trim((string) $ip);
        if ($ip !== '') {
            foreach ((array) ($cfg['subredes'] ?? []) as $rede => $dados) {
                if (str_starts_with($ip, (string) $rede)) {
                    return ['source_id' => (int) $dados[0], 'cidade' => (string) $dados[1]];
                }
            }
        }

        return null;
    }

    public static function eCerrado(?string $hostname, ?string $ip): bool
    {
        return self::fonte($hostname, $ip) !== null;
    }

    public static function cidade(?string $hostname, ?string $ip): ?string
    {
        return self::fonte($hostname, $ip)['cidade'] ?? null;
    }
}

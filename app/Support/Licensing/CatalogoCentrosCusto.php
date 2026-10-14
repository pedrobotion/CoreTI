<?php

namespace App\Support\Licensing;

use App\Models\CoretiCostCenter;

/**
 * Resolve o nome de um destino do rateio a partir do catálogo atual de centros
 * de custo (tela /centros-custo, tabela coreti_cost_centers), tendo o CÓDIGO
 * (unicoop.área, ex.: 01.180) como chave.
 *
 * Nasceu porque o rateio do Office montava os nomes de unidade/departamento a
 * partir do catálogo antigo da intranet (organization_*), que ficou obsoleto.
 * O código continua válido; o nome passa a vir daqui. A resolução é feita em
 * tempo de leitura — nada é regravado nos snapshots, então competências já
 * fechadas também passam a exibir o nome atual.
 *
 * O catálogo é carregado uma vez por instância; reaproveite a mesma instância
 * dentro de uma requisição.
 */
class CatalogoCentrosCusto
{
    /** @var array<string, list<string>> código normalizado => nomes cadastrados */
    private array $porCodigo;

    public function __construct()
    {
        $this->porCodigo = [];

        foreach (CoretiCostCenter::query()->get(['code', 'name']) as $centro) {
            $codigo = self::normalizar($centro->code);
            if ($codigo === '') {
                continue;
            }

            $nome = trim((string) $centro->name);
            if ($nome !== '') {
                $this->porCodigo[$codigo][] = $nome;
            }
        }
    }

    /**
     * Nome do catálogo para um código, quando ele é inequívoco (um único nome
     * cadastrado). Devolve null se o código não existe no catálogo ou tem mais
     * de um nome — nesses casos o chamador mantém o nome antigo (snapshot).
     */
    public function nome(?string $codigo): ?string
    {
        $codigo = self::normalizar($codigo);
        if ($codigo === '' || ! isset($this->porCodigo[$codigo])) {
            return null;
        }

        $nomes = array_values(array_unique($this->porCodigo[$codigo]));

        return count($nomes) === 1 ? $nomes[0] : null;
    }

    /** Nome do catálogo quando resolvível; senão o fallback informado (ou null). */
    public function nomeOu(?string $codigo, ?string $fallback): ?string
    {
        $nome = $this->nome($codigo);
        if ($nome !== null) {
            return $nome;
        }

        $fallback = trim((string) $fallback);

        return $fallback !== '' ? $fallback : null;
    }

    /**
     * Nome do catálogo quando TODOS os códigos de um conjunto apontam para o
     * mesmo nome (inequívoco). Usado pelo resumo por unidade, em que uma unidade
     * pode reunir vários centros de custo: só troca o nome quando não há dúvida.
     *
     * @param  iterable<string|null>  $codigos
     */
    public function nomeUnico(iterable $codigos): ?string
    {
        $nomes = [];
        foreach ($codigos as $codigo) {
            $nome = $this->nome($codigo);
            if ($nome !== null) {
                $nomes[$nome] = true;
            }
        }

        return count($nomes) === 1 ? array_key_first($nomes) : null;
    }

    /** Normaliza "1.226", "01/226", "01 . 226" para "01.226". CNPJ e o resto viram "". */
    public static function normalizar(?string $codigo): string
    {
        if (! preg_match('/^\s*(\d{1,2})\s*[.\/-]\s*(\d{1,3})\s*$/', (string) $codigo, $m)) {
            return '';
        }

        return str_pad((string) ((int) $m[1]), 2, '0', STR_PAD_LEFT)
            . '.'
            . str_pad((string) ((int) $m[2]), 3, '0', STR_PAD_LEFT);
    }
}

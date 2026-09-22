<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices únicos que faltavam nas chaves de firstOrCreate/updateOrCreate.
 *
 * Esses métodos leem e depois escrevem, em duas consultas separadas. Sem
 * restrição no banco, duas execuções simultâneas leem "não existe" ao mesmo
 * tempo e criam duas linhas — e ninguém percebe, porque nenhuma delas falha.
 *
 * O índice transforma corrupção silenciosa em erro visível: a segunda escrita
 * quebra em vez de duplicar. O caso mais exposto é cmdb_discovered_assets, que
 * roda sobre 332 itens a cada ciclo do vCenter.
 *
 * Verificado antes de aplicar: nenhuma das três tem duplicata hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Duas descobertas com o mesmo source_hash são o mesmo objeto do
        // vSphere lido duas vezes.
        $this->criarUnico('cmdb_discovered_assets', ['source_hash'], 'cmdb_discovered_assets_source_hash_unique');

        // O mesmo IP não pode aparecer duas vezes no mesmo ativo.
        $this->criarUnico('cmdb_ip_addresses', ['asset_id', 'ip_address'], 'cmdb_ip_addresses_asset_ip_unique');

        // Nome de rack é a identidade usada na criação rápida.
        $this->criarUnico('cmdb_racks', ['name'], 'cmdb_racks_name_unique');
    }

    public function down(): void
    {
        foreach ([
            ['cmdb_discovered_assets', 'cmdb_discovered_assets_source_hash_unique'],
            ['cmdb_ip_addresses', 'cmdb_ip_addresses_asset_ip_unique'],
            ['cmdb_racks', 'cmdb_racks_name_unique'],
        ] as [$tabela, $indice]) {
            if (Schema::hasTable($tabela) && $this->temIndice($tabela, $indice)) {
                Schema::table($tabela, fn (Blueprint $t) => $t->dropUnique($indice));
            }
        }
    }

    private function criarUnico(string $tabela, array $colunas, string $indice): void
    {
        if (! Schema::hasTable($tabela) || $this->temIndice($tabela, $indice)) {
            return;
        }

        foreach ($colunas as $coluna) {
            if (! Schema::hasColumn($tabela, $coluna)) {
                return;
            }
        }

        /*
         | Aborta se houver duplicata em vez de deixar a migration quebrar com
         | erro do MySQL: a mensagem diz o que limpar e onde.
         */
        $lista = implode(', ', array_map(fn ($c) => "`{$c}`", $colunas));
        $duplicadas = DB::selectOne(
            "SELECT COUNT(*) AS n FROM (SELECT {$lista} FROM `{$tabela}` GROUP BY {$lista} HAVING COUNT(*) > 1) t"
        );

        if ((int) ($duplicadas->n ?? 0) > 0) {
            throw new RuntimeException(
                "Não é possível criar {$indice}: {$tabela} tem {$duplicadas->n} grupo(s) duplicado(s) em ".
                implode('+', $colunas).'. Resolva as duplicatas antes de migrar.'
            );
        }

        Schema::table($tabela, fn (Blueprint $t) => $t->unique($colunas, $indice));
    }

    private function temIndice(string $tabela, string $indice): bool
    {
        return DB::select("SHOW INDEX FROM `{$tabela}` WHERE Key_name = ?", [$indice]) !== [];
    }
};

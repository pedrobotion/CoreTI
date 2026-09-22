<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ponte para o modelo de permissões anterior (uma coluna booleana por módulo
 * em user_module_permissions).
 *
 * Existe para que o código funcione antes E depois da migration que cria
 * user_module_accesses: sem isso, subir a aplicação antes de migrar derruba
 * toda página autenticada de usuário não-admin. Assim que a tabela nova
 * existir, esta classe deixa de ser consultada sozinha.
 *
 * Pode ser removida junto com a tabela legada, depois que a migração de
 * permissões estiver validada em produção.
 */
class LegacyModulePermissions
{
    private static ?bool $novaTabelaExiste = null;

    private static ?bool $tabelaLegadaExiste = null;

    /**
     * A tabela normalizada já foi criada? Memoizado: sem isso seria uma
     * consulta ao schema a cada verificação de permissão.
     */
    public static function novaTabelaDisponivel(): bool
    {
        return self::$novaTabelaExiste ??= Schema::hasTable('user_module_accesses');
    }

    public static function disponivel(): bool
    {
        return self::$tabelaLegadaExiste ??= Schema::hasTable('user_module_permissions');
    }

    /**
     * Abilities equivalentes ao booleano legado: quem tinha o módulo marcado
     * podia ver e administrar dentro dele.
     *
     * @return array<int, string>
     */
    public static function abilitiesFor(User $user, string $module): array
    {
        if (! self::disponivel() || ! ModuleRegistry::exists($module)) {
            return [];
        }

        $row = DB::table('user_module_permissions')->where('user_id', $user->id)->first();

        if ($row === null) {
            return [];
        }

        $ligado = (bool) ($row->{$module} ?? false);

        // CMDB e Network/Datacenter se implicavam mutuamente no modelo antigo.
        if (in_array($module, ['cmdb', 'network_datacenter'], true)) {
            $ligado = (bool) ($row->cmdb ?? false) || (bool) ($row->network_datacenter ?? false);
        }

        if (! $ligado) {
            return [];
        }

        return array_values(array_intersect(
            ['view', 'manage'],
            ModuleRegistry::abilities($module)
        ));
    }

    /**
     * Usado apenas por testes que criam e destroem o schema entre casos.
     */
    public static function esquecerCache(): void
    {
        self::$novaTabelaExiste = null;
        self::$tabelaLegadaExiste = null;
    }
}

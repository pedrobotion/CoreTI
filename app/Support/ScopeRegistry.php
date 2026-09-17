<?php

namespace App\Support;

use App\Models\UnitScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Leitura tipada do config/scopes.php, no mesmo espírito do ModuleRegistry.
 *
 * Quem precisar saber "quais escopos existem" ou "a que escopo pertence esta
 * unidade" passa por aqui, para que a lista não se espalhe por controllers e
 * views como aconteceu com os módulos antes da normalização.
 */
class ScopeRegistry
{
    private const UNIT_MAP_CACHE_KEY = 'scopes:unit-map';

    private static ?bool $tabelasExistem = null;

    /**
     * As tabelas de escopo já foram criadas?
     *
     * Mesmo motivo do LegacyModulePermissions: subir o código antes de rodar
     * a migration não pode derrubar página autenticada. Memoizado para não
     * consultar o schema a cada verificação.
     */
    public static function tabelasDisponiveis(): bool
    {
        return self::$tabelasExistem ??= Schema::hasTable('unit_scopes') && Schema::hasTable('user_scopes');
    }

    /**
     * Usado apenas por testes que criam e destroem o schema entre casos.
     */
    public static function esquecerCache(): void
    {
        self::$tabelasExistem = null;
        self::forgetUnitMap();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return (array) config('scopes.scopes', []);
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $scope): bool
    {
        return array_key_exists($scope, self::all());
    }

    public static function label(string $scope): string
    {
        return (string) (self::all()[$scope]['label'] ?? $scope);
    }

    public static function description(string $scope): string
    {
        return (string) (self::all()[$scope]['description'] ?? '');
    }

    /**
     * Rótulos prontos para um <select>.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $key => $definition) {
            $options[$key] = (string) ($definition['label'] ?? $key);
        }

        return $options;
    }

    /**
     * Escopo de uma unidade da intranet, ou null se ninguém classificou.
     *
     * Devolver null é intencional: a unidade nova fica pendente e aparece para
     * classificação, em vez de cair num escopo por dedução.
     */
    public static function forUnit(?string $unitSourceId): ?string
    {
        if ($unitSourceId === null || trim($unitSourceId) === '') {
            return null;
        }

        return self::unitMap()[trim($unitSourceId)] ?? null;
    }

    /**
     * Escopo a partir do id local da unidade.
     *
     * O resto do CoreTI referencia unidade por organization_units.id, enquanto
     * unit_scopes é chaveada pelo source_id da intranet — esta é a ponte entre
     * as duas formas.
     */
    public static function forUnitId(int|string|null $unitId): ?string
    {
        if ($unitId === null || $unitId === '') {
            return null;
        }

        $sourceId = UnitScope::query()
            ->getConnection()
            ->table('organization_units')
            ->where('id', $unitId)
            ->value('source_id');

        return self::forUnit($sourceId === null ? null : (string) $sourceId);
    }

    /**
     * @return array<string, string>
     */
    public static function unitMap(): array
    {
        if (! self::tabelasDisponiveis()) {
            return [];
        }

        return Cache::remember(
            self::UNIT_MAP_CACHE_KEY,
            now()->addMinutes(10),
            fn () => UnitScope::query()->pluck('scope', 'organization_unit_source_id')->all()
        );
    }

    public static function forgetUnitMap(): void
    {
        Cache::forget(self::UNIT_MAP_CACHE_KEY);
    }
}

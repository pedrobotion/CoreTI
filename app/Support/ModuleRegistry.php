<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Leitura tipada do config/modules.php.
 *
 * Tudo que precisa saber "quais módulos existem" ou "quais permissões um
 * módulo aceita" deve passar por aqui, para que a lista não volte a ser
 * duplicada em controllers, middlewares e views.
 */
class ModuleRegistry
{
    public const ABILITY_VIEW = 'view';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return (array) config('modules.modules', []);
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $module): bool
    {
        return array_key_exists($module, self::all());
    }

    public static function definition(string $module): ?array
    {
        return self::all()[$module] ?? null;
    }

    public static function label(string $module): string
    {
        return (string) (self::definition($module)['label'] ?? $module);
    }

    /**
     * Abilities aceitas pelo módulo, sempre incluindo 'view'.
     */
    public static function abilities(string $module): array
    {
        $abilities = (array) (self::definition($module)['abilities'] ?? [self::ABILITY_VIEW]);
        $abilities = array_values(array_unique(array_merge([self::ABILITY_VIEW], $abilities)));

        return array_values(array_intersect($abilities, array_keys(self::abilityLabels())));
    }

    public static function supportsAbility(string $module, string $ability): bool
    {
        return in_array($ability, self::abilities($module), true);
    }

    /**
     * @return array<string, string>
     */
    public static function abilityLabels(): array
    {
        return (array) config('modules.abilities', [self::ABILITY_VIEW => 'Visualizar']);
    }

    public static function abilityLabel(string $module, string $ability): string
    {
        $custom = self::definition($module)['ability_labels'][$ability] ?? null;

        return (string) ($custom ?? self::abilityLabels()[$ability] ?? $ability);
    }

    /**
     * Abilities que uma concessão arrasta junto dentro do mesmo módulo.
     * 'manage' sempre implica todas as demais; 'implies' permite casos extras.
     */
    public static function impliedAbilities(string $module, string $ability): array
    {
        $implied = [$ability];

        if ($ability === 'manage') {
            $implied = self::abilities($module);
        }

        $extra = (array) (self::definition($module)['implies'][$ability] ?? []);

        return array_values(array_unique(array_merge($implied, $extra, [self::ABILITY_VIEW])));
    }

    /**
     * Normaliza chaves legadas para a chave atual do módulo.
     */
    public static function resolveKey(string $module): ?string
    {
        $module = trim($module);

        if (self::exists($module)) {
            return $module;
        }

        foreach (self::all() as $key => $definition) {
            if (in_array($module, (array) ($definition['aliases'] ?? []), true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Módulos agrupados por 'group', para renderizar a tela de acessos.
     *
     * @return Collection<string, Collection>
     */
    public static function grouped(): Collection
    {
        return collect(self::all())
            ->map(fn (array $definition, string $key) => array_merge($definition, ['key' => $key]))
            ->groupBy(fn (array $definition) => $definition['group'] ?? 'Outros');
    }

    /**
     * Aceita "modulo" ou "modulo.ability" e devolve [modulo, ability].
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parse(string $expression): ?array
    {
        $expression = trim($expression);

        if ($expression === '') {
            return null;
        }

        $ability = self::ABILITY_VIEW;

        if (str_contains($expression, '.')) {
            [$expression, $ability] = explode('.', $expression, 2);
        }

        $module = self::resolveKey($expression);

        if ($module === null || ! self::supportsAbility($module, $ability)) {
            return null;
        }

        return [$module, $ability];
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserModuleAccess;
use App\Support\LegacyModulePermissions;
use App\Support\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Concessão e revogação de permissões de módulo.
 *
 * É o único ponto que escreve em user_module_accesses — controllers e comandos
 * devem passar por aqui para que 'manage' continue implicando as demais
 * abilities e para que o registro de quem concedeu não se perca.
 */
class ModuleAccessService
{
    /**
     * Substitui todas as permissões do usuário pelo mapa informado.
     *
     * @param  array<string, array<int, string>>  $desired  ['admissao' => ['view','request']]
     */
    public function sync(User $user, array $desired, ?User $grantedBy = null): void
    {
        if (! LegacyModulePermissions::novaTabelaDisponivel()) {
            throw new RuntimeException(
                'A tabela user_module_accesses ainda não existe. Rode "php artisan migrate" antes de alterar acessos.'
            );
        }

        $normalized = $this->normalize($desired);

        DB::transaction(function () use ($user, $normalized, $grantedBy): void {
            $existing = UserModuleAccess::where('user_id', $user->id)->get();

            $existingKeys = $existing
                ->mapWithKeys(fn (UserModuleAccess $row) => [$row->module.'.'.$row->ability => $row])
                ->all();

            $desiredKeys = [];
            foreach ($normalized as $module => $abilities) {
                foreach ($abilities as $ability) {
                    $desiredKeys[$module.'.'.$ability] = [$module, $ability];
                }
            }

            $toRemove = array_diff_key($existingKeys, $desiredKeys);
            if ($toRemove !== []) {
                UserModuleAccess::whereIn('id', array_map(fn ($row) => $row->id, $toRemove))->delete();
            }

            $toAdd = array_diff_key($desiredKeys, $existingKeys);
            foreach ($toAdd as [$module, $ability]) {
                UserModuleAccess::create([
                    'user_id' => $user->id,
                    'module' => $module,
                    'ability' => $ability,
                    'granted_by' => $grantedBy?->id,
                ]);
            }
        });

        $user->unsetRelation('moduleAccesses');
    }

    public function grant(User $user, string $module, string $ability = 'view', ?User $grantedBy = null): void
    {
        $current = $this->currentMap($user);
        $current[$module] = array_values(array_unique(array_merge($current[$module] ?? [], [$ability])));

        $this->sync($user, $current, $grantedBy);
    }

    public function revokeModule(User $user, string $module): void
    {
        $current = $this->currentMap($user);
        unset($current[$module]);

        $this->sync($user, $current);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function currentMap(User $user): array
    {
        return $user->moduleAccesses()
            ->get()
            ->groupBy('module')
            ->map(fn ($rows) => $rows->pluck('ability')->unique()->values()->all())
            ->all();
    }

    /**
     * Descarta módulos/abilities inexistentes e aplica as implicações
     * declaradas no registry (ex.: 'manage' arrasta 'view').
     *
     * @param  array<string, array<int, string>>  $desired
     * @return array<string, array<int, string>>
     */
    private function normalize(array $desired): array
    {
        $normalized = [];

        foreach ($desired as $module => $abilities) {
            $moduleKey = ModuleRegistry::resolveKey((string) $module);

            if ($moduleKey === null) {
                continue;
            }

            $resolved = [];

            foreach ((array) $abilities as $ability) {
                $ability = (string) $ability;

                if (! ModuleRegistry::supportsAbility($moduleKey, $ability)) {
                    continue;
                }

                foreach (ModuleRegistry::impliedAbilities($moduleKey, $ability) as $implied) {
                    if (ModuleRegistry::supportsAbility($moduleKey, $implied)) {
                        $resolved[] = $implied;
                    }
                }
            }

            if ($resolved !== []) {
                $normalized[$moduleKey] = array_values(array_unique($resolved));
            }
        }

        return $normalized;
    }
}

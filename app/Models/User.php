<?php

namespace App\Models;

use App\Support\LegacyModulePermissions;
use App\Support\ModuleRegistry;
use App\Support\ScopeRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const MASTER_USER_ID = 2;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',
        'reset_token',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Permissões legadas (uma coluna booleana por módulo).
     *
     * Mantida apenas para consulta histórica e rollback: a fonte de verdade
     * passou a ser moduleAccesses().
     *
     * @deprecated Use moduleAccesses().
     */
    public function modulePermissions(): HasOne
    {
        return $this->hasOne(UserModulePermission::class);
    }

    public function moduleAccesses(): HasMany
    {
        return $this->hasMany(UserModuleAccess::class);
    }

    /**
     * Acesso de leitura ao módulo — assinatura preservada para não quebrar
     * o middleware, o menu lateral e as views existentes.
     */
    public function hasModuleAccess(string $module): bool
    {
        return $this->canModule($module, ModuleRegistry::ABILITY_VIEW);
    }

    /**
     * Verificação por nível. Aceita 'admissao' ou 'admissao.request'.
     */
    public function canModule(string $module, ?string $ability = null): bool
    {
        $parsed = ModuleRegistry::parse(
            $ability === null ? $module : $module.'.'.$ability
        );

        if ($parsed === null) {
            return false;
        }

        [$moduleKey, $abilityKey] = $parsed;

        if ($this->isMasterAccount()) {
            return true;
        }

        if ($this->role === 'admin' && config('modules.legacy_admin_bypass', true)) {
            return true;
        }

        return $this->grantedAbilities($moduleKey)->contains($abilityKey);
    }

    /**
     * Verdadeiro se o usuário tiver qualquer uma das expressões informadas.
     *
     * @param  array<int, string>  $expressions  ['cmdb', 'network_datacenter.manage']
     */
    public function canAnyModule(array $expressions): bool
    {
        foreach ($expressions as $expression) {
            if ($this->canModule((string) $expression)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function grantedAbilities(string $module): \Illuminate\Support\Collection
    {
        // Antes da migration que cria user_module_accesses, cai no modelo
        // legado — do contrário a aplicação quebra se subir antes de migrar.
        if (! LegacyModulePermissions::novaTabelaDisponivel()) {
            return collect(LegacyModulePermissions::abilitiesFor($this, $module));
        }

        return $this->relationLoaded('moduleAccesses')
            ? $this->moduleAccesses->where('module', $module)->pluck('ability')
            : $this->moduleAccesses()->where('module', $module)->pluck('ability');
    }

    /**
     * Mapa completo de permissões concedidas, para a tela de administração.
     *
     * @return array<string, array<int, string>>
     */
    public function moduleAccessMap(): array
    {
        if (! LegacyModulePermissions::novaTabelaDisponivel()) {
            $mapa = [];
            foreach (ModuleRegistry::keys() as $module) {
                $abilities = LegacyModulePermissions::abilitiesFor($this, $module);
                if ($abilities !== []) {
                    $mapa[$module] = $abilities;
                }
            }

            return $mapa;
        }

        // Usa a relação já carregada quando houver: a tela de administração
        // faz eager load e sem isto seria uma consulta por usuário listado.
        $rows = $this->relationLoaded('moduleAccesses')
            ? $this->moduleAccesses
            : $this->moduleAccesses()->get();

        return $rows
            ->groupBy('module')
            ->map(fn ($grupo) => $grupo->pluck('ability')->unique()->values()->all())
            ->all();
    }

    public function isMasterAccount(): bool
    {
        return (int) $this->id === self::MASTER_USER_ID;
    }

    /**
     * Escopos operacionais concedidos (Sede, Cerrado).
     *
     * Separado das permissões de módulo: o módulo diz qual tela se abre, o
     * escopo diz de qual operação são os registros dentro dela.
     */
    public function scopes(): HasMany
    {
        return $this->hasMany(UserScope::class);
    }

    /**
     * Escopos que este usuário enxerga.
     *
     * Segue o mesmo bypass das permissões de módulo, para que administrador
     * não perca acesso ao que já via. Quando 'legacy_admin_bypass' for
     * desligado, a concessão explícita gravada na migration assume.
     *
     * @return array<int, string>
     */
    public function visibleScopes(): array
    {
        if ($this->isMasterAccount() || ($this->role === 'admin' && config('modules.legacy_admin_bypass', true))) {
            return ScopeRegistry::keys();
        }

        // Antes da migration que cria user_scopes, consultar a relação
        // derrubaria a página. Sem escopo concedido não se vê nada, que é o
        // mesmo resultado — só que sem erro.
        if (! ScopeRegistry::tabelasDisponiveis()) {
            return [];
        }

        $granted = $this->relationLoaded('scopes')
            ? $this->scopes->pluck('scope')
            : $this->scopes()->pluck('scope');

        return $granted
            ->filter(fn ($scope) => ScopeRegistry::exists((string) $scope))
            ->unique()
            ->values()
            ->all();
    }

    public function canScope(?string $scope): bool
    {
        if ($scope === null || $scope === '') {
            return false;
        }

        return in_array($scope, $this->visibleScopes(), true);
    }
}

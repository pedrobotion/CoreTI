<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\ScopeRegistry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recorte Sede x Cerrado para os models que carregam a coluna 'scope'.
 *
 * O filtro é explícito — ->visibleTo($user) na query — e não um global scope.
 * O gestaoTI usava global scope e, por causa disso, precisava de
 * withoutGlobalScopes() justamente nos pontos em que a regra de negócio
 * exigia enxergar o registro inteiro (conferir se uma linha já tem vínculo
 * ativo, por exemplo). Filtro invisível que precisa ser desligado à mão é
 * fácil de esquecer de desligar.
 */
trait HasOperationalScope
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $scopes = $user?->visibleScopes() ?? [];

        if ($scopes === []) {
            // Sem escopo concedido não se vê nada. Falha fechada.
            return $query->whereRaw('1 = 0');
        }

        if (count($scopes) === count(ScopeRegistry::keys())) {
            return $query;
        }

        return $query->whereIn($query->getModel()->getTable().'.scope', $scopes);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInScope(Builder $query, ?string $scope): Builder
    {
        if ($scope === null || $scope === '' || ! ScopeRegistry::exists($scope)) {
            return $query;
        }

        return $query->where($query->getModel()->getTable().'.scope', $scope);
    }

    public function getScopeLabelAttribute(): string
    {
        return ScopeRegistry::label((string) $this->scope);
    }
}

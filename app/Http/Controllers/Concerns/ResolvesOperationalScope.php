<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\ScopeRegistry;
use Illuminate\Validation\ValidationException;

/**
 * Leitura e conferência do escopo (Sede / Cerrado) nos controllers.
 *
 * Centralizado para que a checagem não dependa de cada controller lembrar de
 * fazê-la: gravar num escopo que o usuário não tem é justamente o furo que o
 * isolamento existe para impedir.
 */
trait ResolvesOperationalScope
{
    /**
     * Escopos que o usuário pode escolher num formulário.
     *
     * @return array<string, string>
     */
    protected function scopeOptions(?User $user): array
    {
        $visible = $user?->visibleScopes() ?? [];

        return array_intersect_key(ScopeRegistry::options(), array_flip($visible));
    }

    /**
     * Valida o escopo enviado no formulário.
     *
     * Quem só enxerga um escopo não precisa escolher — o campo nem aparece na
     * tela, e o único escopo é assumido.
     *
     * @throws ValidationException
     */
    protected function resolveScope(?User $user, ?string $requested): string
    {
        $options = $this->scopeOptions($user);

        if ($options === []) {
            throw ValidationException::withMessages([
                'scope' => 'Seu usuário não tem nenhum escopo operacional concedido. Peça a liberação de Sede ou Cerrado.',
            ]);
        }

        if ($requested === null || $requested === '') {
            if (count($options) === 1) {
                return (string) array_key_first($options);
            }

            throw ValidationException::withMessages([
                'scope' => 'Selecione a operação (Sede ou Cerrado).',
            ]);
        }

        if (! array_key_exists($requested, $options)) {
            throw ValidationException::withMessages([
                'scope' => 'Você não tem acesso a essa operação.',
            ]);
        }

        return $requested;
    }

    /**
     * Impede abrir ou alterar registro de escopo que o usuário não enxerga.
     *
     * Usado no edit/update: sem isso, a listagem filtraria corretamente mas a
     * URL direta do registro continuaria acessível.
     */
    protected function ensureScopeIsVisible(?User $user, ?string $scope): void
    {
        abort_unless($user?->canScope($scope), 403, 'Este registro pertence a uma operação fora do seu acesso.');
    }
}

<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Paginacao;
use Illuminate\Http\Request;

/**
 * Quantidade de itens por página escolhida pelo usuário.
 *
 * A lista de opções vive em App\Support\Paginacao — ver a nota lá sobre por
 * que ela não é uma constante desta trait.
 */
trait PaginacaoConfiguravel
{
    protected function porPagina(Request $request, int $padrao = Paginacao::PADRAO): int
    {
        $pedido = $request->integer('per_page', $padrao);

        return in_array((int) $pedido, Paginacao::OPCOES, true) ? (int) $pedido : $padrao;
    }
}

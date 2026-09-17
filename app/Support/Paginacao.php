<?php

namespace App\Support;

/**
 * Opções de itens por página das listagens.
 *
 * Fica numa classe, e não na trait que a usa, porque o PHP não permite ler
 * constante de trait diretamente (`Trait::CONST`) — e as views precisam
 * montar o <select> a partir dela.
 *
 * A lista é fechada de propósito: aceitar qualquer número vindo da URL deixa
 * alguém pedir ?per_page=100000 e derrubar a página, e o servidor junto.
 */
final class Paginacao
{
    public const OPCOES = [20, 50, 100, 200];

    public const PADRAO = 20;

    public static function normalizar(int|string|null $pedido): int
    {
        $pedido = (int) $pedido;

        return in_array($pedido, self::OPCOES, true) ? $pedido : self::PADRAO;
    }
}

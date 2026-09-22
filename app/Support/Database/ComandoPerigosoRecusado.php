<?php

namespace App\Support\Database;

use RuntimeException;

/**
 * Exceção própria do guarda.
 *
 * Existe para ser distinguível de QueryException — que estende PDOException,
 * que estende RuntimeException. Sem um tipo próprio, não há como saber se um
 * comando foi recusado pelo guarda ou se o banco é que reclamou, nem em
 * teste nem em log.
 */
class ComandoPerigosoRecusado extends RuntimeException
{
    public function __construct(public readonly string $comando)
    {
        parent::__construct('Comando recusado: a aplicação web não emite este tipo de instrução no banco.');
    }
}

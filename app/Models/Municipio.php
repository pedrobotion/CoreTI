<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Município por código IBGE.
 *
 * Existe para o termo poder ser assinado na cidade da unidade do colaborador,
 * em vez de sair sempre em Mandaguari. As unidades guardam o código IBGE
 * (organization_units.municipality_id), mas não o nome da cidade.
 */
class Municipio extends Model
{
    protected $table = 'municipios';

    protected $fillable = ['codigo_ibge', 'nome', 'uf'];

    public function getNomeComUfAttribute(): string
    {
        return trim($this->nome.($this->uf ? ' - '.$this->uf : ''));
    }
}

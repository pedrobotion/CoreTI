<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkContratante extends Model
{
    protected $table = 'network_contratantes';

    protected $fillable = [
        'nome_fantasia',
        'razao_social',
        'cnpj',
        'inscricao_estadual',
        'observacao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(NetworkServicoConectividade::class, 'contratante_id');
    }
}

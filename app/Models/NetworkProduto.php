<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkProduto extends Model
{
    protected $table = 'network_produtos';

    protected $fillable = [
        'nome',
        'descricao',
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
        return $this->hasMany(NetworkServicoConectividade::class, 'produto_id');
    }
}

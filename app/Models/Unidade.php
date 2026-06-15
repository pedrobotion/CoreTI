<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unidade extends Model
{
    protected $table = 'unidades';
    protected $primaryKey = 'id_unidades';
    public $timestamps = false;

    protected $fillable = [
        'unidade',
        'cnpj',
        'endereco',
    ];

    public function circuitos(): HasMany
    {
        return $this->hasMany(CircuitUnit::class, 'id_unidades', 'id_unidades');
    }
}

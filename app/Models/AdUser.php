<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdUser extends Model
{
    protected $table = 'ad_users';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'e_ativo' => 'boolean',
        'criado_em' => 'datetime',
        'ultima_modificacao_senha' => 'datetime',
        'last_sync' => 'datetime',
        'data_desativacao' => 'datetime',
        'excluido' => 'boolean',
        'excluido_em' => 'datetime',
    ];

    /** Contas ainda presentes no Active Directory. */
    public function scopeVigentes($query)
    {
        return $query->where('excluido', false);
    }

    /** Contas já removidas do AD, mantidas apenas como histórico. */
    public function scopeExcluidas($query)
    {
        return $query->where('excluido', true);
    }
}

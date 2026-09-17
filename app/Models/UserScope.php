<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Escopo operacional concedido a um usuário.
 *
 * Separado de user_module_accesses de propósito: módulo responde "pode abrir
 * a tela de linhas?" e escopo responde "de qual operação?". Misturar os dois
 * numa string só ('telefonia.cerrado.manage') faria o registro de módulos
 * crescer pelo produto das duas listas.
 */
class UserScope extends Model
{
    protected $table = 'user_scopes';

    protected $fillable = [
        'user_id',
        'scope',
        'granted_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}

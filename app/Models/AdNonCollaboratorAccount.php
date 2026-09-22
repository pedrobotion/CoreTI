<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdNonCollaboratorAccount extends Model
{
    protected $table = 'ad_non_collaborator_accounts';

    protected $guarded = [];

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /** Nomes em minúsculo, para comparação sem depender da caixa. */
    public static function nomes(): array
    {
        return static::query()->pluck('nome_usuario')
            ->map(fn ($n) => mb_strtolower(trim($n)))
            ->all();
    }
}

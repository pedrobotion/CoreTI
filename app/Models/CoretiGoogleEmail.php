<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoretiGoogleEmail extends Model
{
    protected $table = 'coreti_google_emails';

    protected $fillable = [
        'email',
        'nome',
        'status_google',
        'nome_usuario',
        'ad_user_id',
        'ad_unidade_setor_original',
        'tipo_local',
        'nome_local',
        'nome_local_normalizado',
        'unicoop',
        'area',
        'centro_custo',
        'centro_custo_nome',
        'mapeamento_status',
        'mapeamento_motivo',
        'importado_em',
        'atualizado_rateio_em',
    ];

    protected $casts = [
        'importado_em' => 'datetime',
        'atualizado_rateio_em' => 'datetime',
    ];
}

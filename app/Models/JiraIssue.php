<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JiraIssue extends Model
{
    protected $table = 'jiras';

    protected $fillable = [
        'chave',
        'resumo',
        'relator_nome',
        'relator_email',
        'responsavel_nome',
        'prioridade',
        'status',
        'unidade',
        'departamento',
        'tipo_requisicao',
        'catalogo',
        'squad',
        'data_hora_criacao',
        'data_hora_atualizacao',
        'data_hora_resolucao',
        'data_hora_resolucao_c',
        'meta_tempo_primeira_resposta',
        'tempo_primeira_resposta',
        'tempo_inicio_sla',
        'tempo_fim_sla',
        'sla_goalDuration',
        'sla_elapsedTime',
        'sla_remainingTime',
        'currentstatus_data_hora',
        'currentstatus_status',
        'tempo_primeira_resposta_inicio',
        'tempo_primeira_resposta_goalDuration',
        'tempo_primeira_resposta_elapsedTime',
        'tempo_primeira_resposta_remainingTime',
        'votes',
        'tempo_sla_final_goalDuration',
        'tempo_sla_final_elapsedTime',
        'tempo_sla_final_remainingTime',
    ];

    protected function casts(): array
    {
        return [
            'data_hora_criacao' => 'datetime',
            'data_hora_atualizacao' => 'datetime',
            'data_hora_resolucao' => 'datetime',
            'data_hora_resolucao_c' => 'datetime',
            'meta_tempo_primeira_resposta' => 'datetime',
            'tempo_primeira_resposta' => 'datetime',
            'tempo_inicio_sla' => 'datetime',
            'tempo_fim_sla' => 'datetime',
            'currentstatus_data_hora' => 'datetime',
            'tempo_primeira_resposta_inicio' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BancadaPendenciaAlert extends Model
{
    protected $table = 'bancada_pendencia_alerts';

    protected $fillable = [
        'bancada_equipment_id', 'tipo', 'situacao', 'dias',
        'jira_issue_key', 'jira_issue_url',
        'primeiro_detectado_em', 'ultimo_alerta_em', 'resolvido_em',
    ];

    protected function casts(): array
    {
        return [
            'dias' => 'integer',
            'primeiro_detectado_em' => 'datetime',
            'ultimo_alerta_em' => 'datetime',
            'resolvido_em' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BancadaEquipment::class, 'bancada_equipment_id');
    }
}

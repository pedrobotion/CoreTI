<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ServiceDeskEmail extends Model
{
    protected $fillable = [
        'scope',
        'email',
        'matricula',
        'colaborador_nome',
        'id_pessoa',
        'service_desk_email_cost_center_id',
        'centro_custo',
        'unicoop_sede',
        'area_sede',
        'centro_custo_manual',
        'centro_custo_manual_at',
        'data_inclusao',
        'data_desativacao',
        'ativo',
        'observacao',
        'legacy_source_table',
        'legacy_source_id',
        'legacy_excluido',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'centro_custo_manual' => 'boolean',
            'centro_custo_manual_at' => 'datetime',
            'data_inclusao' => 'date',
            'data_desativacao' => 'date',
            'legacy_excluido' => 'boolean',
        ];
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskEmailCostCenter::class, 'service_desk_email_cost_center_id');
    }

    public function setColaboradorNomeAttribute(?string $value): void
    {
        $name = trim((string) $value);
        if ($name === '') {
            $this->attributes['colaborador_nome'] = $name;
            return;
        }

        $this->attributes['colaborador_nome'] = Str::of(mb_strtolower($name, 'UTF-8'))
            ->title()
            ->toString();
    }
}

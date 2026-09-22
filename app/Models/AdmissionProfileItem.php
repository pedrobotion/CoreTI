<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionProfileItem extends Model
{
    protected $fillable = [
        'admission_access_profile_id',
        'task_type',
        'obrigatorio',
        'sla_dias_antes',
        'observacao',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'obrigatorio' => 'boolean',
            'sla_dias_antes' => 'integer',
            'config' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AdmissionAccessProfile::class, 'admission_access_profile_id');
    }

    public function label(): string
    {
        return (string) config("admissao.task_types.{$this->task_type}.label", $this->task_type);
    }

    public function area(): string
    {
        return (string) config("admissao.task_types.{$this->task_type}.area", 'ti');
    }

    public function slaDiasAntes(): int
    {
        return (int) ($this->sla_dias_antes
            ?? config("admissao.task_types.{$this->task_type}.sla_dias_antes", 3));
    }
}

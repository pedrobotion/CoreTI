<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionCaseEvent extends Model
{
    protected $fillable = [
        'admission_case_id',
        'admission_task_id',
        'event',
        'descricao',
        'contexto',
        'user_id',
    ];

    protected function casts(): array
    {
        return ['contexto' => 'array'];
    }

    public function admissionCase(): BelongsTo
    {
        return $this->belongsTo(AdmissionCase::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AdmissionTask::class, 'admission_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

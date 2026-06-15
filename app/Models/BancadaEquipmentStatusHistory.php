<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BancadaEquipmentStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bancada_equipment_id',
        'status',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BancadaEquipment::class, 'bancada_equipment_id');
    }
}


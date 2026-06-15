<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModulePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'servicedesk',
        'unidades',
        'aplicativos',
        'bancada',
        'administrativo',
    ];

    protected function casts(): array
    {
        return [
            'servicedesk' => 'boolean',
            'unidades' => 'boolean',
            'aplicativos' => 'boolean',
            'bancada' => 'boolean',
            'administrativo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}


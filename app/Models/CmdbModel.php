<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CmdbModel extends Model
{
    use SoftDeletes;

    protected $table = 'cmdb_models';

    protected $fillable = [
        'manufacturer_id',
        'name',
        'slug',
        'asset_type',
        'part_number',
        'rack_units',
        'eol_date',
        'eos_date',
        'support_end_date',
        'manual_url',
        'image_url',
        'power_consumption_watts',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'manufacturer_id' => 'integer',
        'rack_units' => 'integer',
        'power_consumption_watts' => 'integer',
        'eol_date' => 'date',
        'eos_date' => 'date',
        'support_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $model): void {
            if (! filled($model->slug) && filled($model->name)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(CmdbManufacturer::class, 'manufacturer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

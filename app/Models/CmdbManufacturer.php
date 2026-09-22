<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CmdbManufacturer extends Model
{
    use SoftDeletes;

    protected $table = 'cmdb_manufacturers';

    protected $fillable = [
        'name',
        'slug',
        'website',
        'support_url',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $manufacturer): void {
            if (! filled($manufacturer->slug) && filled($manufacturer->name)) {
                $manufacturer->slug = Str::slug($manufacturer->name);
            }
        });
    }

    public function models(): HasMany
    {
        return $this->hasMany(CmdbModel::class, 'manufacturer_id')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contadores de faturamento de uma impressora numa competência.
 *
 * billing_date é a data contábil da leitura, não a data em que o coletor
 * leu — é ela que se confere contra a nota do fornecedor.
 */
class PrinterMeter extends Model
{
    protected $table = 'printer_meters';

    protected $fillable = [
        'printer_id', 'billing_date', 'reading_date', 'reading_date_time',
        'a4_mono', 'a4_colour', 'engine_cycles', 'scans', 'non_copy_scans',
        'mono_small', 'mono_large', 'colour_small', 'colour_large',
        'mono_tier', 'colour_tier1', 'colour_tier2', 'colour_tier3',
        'mono_pages', 'colour_pages', 'duplex', 'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'billing_date' => 'date',
            'reading_date' => 'datetime',
            'reading_date_time' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    /** Total impresso, que é o que a cobrança por página olha. */
    public function getTotalPaginasAttribute(): int
    {
        return (int) $this->mono_pages + (int) $this->colour_pages;
    }
}

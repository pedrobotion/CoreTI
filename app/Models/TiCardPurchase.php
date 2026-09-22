<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiCardPurchase extends Model
{
    protected $table = 'ti_card_purchases';

    protected $fillable = [
        'ti_card_id',
        'purchase_name',
        'purchase_value',
        'billing_type',
        'currency',
        'purchase_date',
        'observacao',
        'invoice_original_name',
        'invoice_storage_path',
        'invoice_mime_type',
        'invoice_uploaded_at',
        'invoice_uploaded_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_value' => 'decimal:2',
            'invoice_uploaded_at' => 'datetime',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(TiCard::class, 'ti_card_id');
    }

    public function invoiceUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invoice_uploaded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

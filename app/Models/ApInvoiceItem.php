<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApInvoiceItem extends Model
{
    /** @use HasFactory<\Database\Factories\ApInvoiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'ap_invoice_id',
        'account_id',
        'description',
        'quantity',
        'unit_price',
        'total_bdt',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_bdt' => 'decimal:2',
    ];

    /**
     * Get the AP invoice that owns the item.
     */
    public function apInvoice(): BelongsTo
    {
        return $this->belongsTo(ApInvoice::class);
    }

    /**
     * Get the expense account for this item.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}

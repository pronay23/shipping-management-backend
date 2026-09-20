<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyReceiptItem extends Model
{
    /** @use HasFactory<\Database\Factories\MoneyReceiptItemFactory> */
    use HasFactory;

    protected $fillable = [
        'money_receipt_id',
        'key',
        'label',
        'qty_20',
        'qty_40',
        'rate_usd',
        'rate_bdt',
        'total_usd',
    ];

    protected $casts = [
        'qty_20' => 'integer',
        'qty_40' => 'integer',
        'rate_usd' => 'decimal:4',
        'rate_bdt' => 'decimal:4',
        'total_usd' => 'decimal:2',
    ];

    /**
     * Get the money receipt that owns the item.
     */
    public function moneyReceipt(): BelongsTo
    {
        return $this->belongsTo(MoneyReceipt::class);
    }
}
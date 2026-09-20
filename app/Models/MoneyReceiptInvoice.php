<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyReceiptInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\MoneyReceiptInvoiceFactory> */
    use HasFactory;

    protected $table = 'money_receipt_invoice';

    protected $fillable = [
        'money_receipt_id',
        'bill_of_lading_id',
        'invoice_id',
        'paid_amount',
    ];

    protected $casts = [
        'paid_amount' => 'decimal:2',
    ];

    /**
     * Get the money receipt that owns the allocation.
     */
    public function moneyReceipt(): BelongsTo
    {
        return $this->belongsTo(MoneyReceipt::class);
    }

    /**
     * Get the bill of lading linked to this allocation.
     */
    public function billOfLading(): BelongsTo
    {
        return $this->belongsTo(BillOfLading::class);
    }

    /**
     * Get the invoice linked to this allocation.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
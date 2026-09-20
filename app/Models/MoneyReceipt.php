<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoneyReceipt extends Model
{
    /** @use HasFactory<\Database\Factories\MoneyReceiptFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'money_receipt_number',
        'money_receipt_date',
        'bl_number',
        'customer_name',
        'vessel',
        'voyage',
        'registration_no',
        'containers',
        'exchange_rate',
        'amount_in_words',
        'total_usd',
        'total_bdt',
        'payment_term',
    ];

    protected $casts = [
        'money_receipt_date' => 'date',
        'exchange_rate' => 'decimal:4',
        'total_usd' => 'decimal:2',
        'total_bdt' => 'decimal:2',
    ];

    /**
     * Get the line items for the money receipt.
     */
    public function items(): HasMany
    {
        return $this->hasMany(MoneyReceiptItem::class);
    }

    /**
     * Get the invoice allocations for the money receipt.
     */
    public function invoiceLinks(): HasMany
    {
        return $this->hasMany(MoneyReceiptInvoice::class);
    }

    /**
     * Get the invoices covered by this money receipt.
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'money_receipt_invoice')
            ->withPivot('paid_amount')
            ->withTimestamps();
    }
}
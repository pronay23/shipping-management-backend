<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'invoice_number',
        'invoice_date',
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
        'remarks',
        'received',
        'due',
        'status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'exchange_rate' => 'decimal:4',
        'total_usd' => 'decimal:2',
        'total_bdt' => 'decimal:2',
        'received' => 'decimal:2',
        'due' => 'decimal:2',
        'status' => InvoiceStatus::class,
    ];

    /**
     * Get the bank details for the invoice.
     */
    public function bankDetails(): HasOne
    {
        return $this->hasOne(InvoiceBankDetail::class);
    }

    /**
     * Get the line items for the invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the money receipt allocations for this invoice.
     */
    public function invoiceLinks(): HasMany
    {
        return $this->hasMany(MoneyReceiptInvoice::class);
    }
}
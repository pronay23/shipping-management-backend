<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\ApInvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'ap_invoice_number',
        'invoice_date',
        'due_date',
        'reference',
        'description',
        'total_bdt',
        'discount_amount',
        'tax_amount',
        'net_amount',
        'paid_amount',
        'due_amount',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'total_bdt' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOIDED = 'voided';

    /**
     * Get the vendor for this invoice.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the line items for this invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ApInvoiceItem::class);
    }

    /**
     * Get the payment allocations for this invoice.
     */
    public function paymentLinks(): HasMany
    {
        return $this->hasMany(ApPaymentInvoice::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApPayment extends Model
{
    /** @use HasFactory<\Database\Factories\ApPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'ap_payment_number',
        'payment_date',
        'payment_method',
        'bank_account_id',
        'reference_number',
        'amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the vendor for this payment.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the invoice allocations for this payment.
     */
    public function invoiceLinks(): HasMany
    {
        return $this->hasMany(ApPaymentInvoice::class);
    }

    /**
     * Get the invoices covered by this payment.
     */
    public function invoices(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ApInvoice::class, 'ap_payment_invoices')
            ->withPivot('paid_amount')
            ->withTimestamps();
    }
}

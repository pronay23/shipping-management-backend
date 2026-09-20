<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApPaymentInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\ApPaymentInvoiceFactory> */
    use HasFactory;

    protected $table = 'ap_payment_invoices';

    protected $fillable = [
        'ap_payment_id',
        'ap_invoice_id',
        'paid_amount',
    ];

    protected $casts = [
        'paid_amount' => 'decimal:2',
    ];

    /**
     * Get the AP payment that owns the allocation.
     */
    public function apPayment(): BelongsTo
    {
        return $this->belongsTo(ApPayment::class);
    }

    /**
     * Get the AP invoice linked to this allocation.
     */
    public function apInvoice(): BelongsTo
    {
        return $this->belongsTo(ApInvoice::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceBankDetail extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceBankDetailFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'account_name',
        'rd_account_no',
        'bank_name',
        'branch_name',
        'swift_code',
        'routing_no',
        'address',
    ];

    /**
     * Get the invoice that owns the bank details.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
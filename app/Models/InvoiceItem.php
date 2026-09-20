<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
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
     * Get the invoice that owns the item.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Compute the line total in BDT without storing it, mirroring the
     * frontend resolution order in the invoice page.
     */
    public function getTotalBdtAttribute(): ?float
    {
        $qty20 = $this->qty_20 !== null ? (int) $this->qty_20 : 0;
        $qty40 = $this->qty_40 !== null ? (int) $this->qty_40 : 0;
        $qty = $qty20 + $qty40;

        $rateBdt = $this->rate_bdt !== null ? (float) $this->rate_bdt : null;
        $rateUsd = $this->rate_usd !== null ? (float) $this->rate_usd : null;
        $exRate = $this->invoice?->exchange_rate !== null ? (float) $this->invoice->exchange_rate : null;

        if ($rateUsd && $rateBdt) {
            $base = $qty > 0 ? $qty * $rateUsd : $rateUsd;
            return $base * $rateBdt;
        }

        if ($rateBdt) {
            return $qty > 0 ? $qty * $rateBdt : $rateBdt;
        }

        if ($rateUsd) {
            $effectiveEx = $exRate ?? 1.0;
            return ($qty > 0 ? $qty * $rateUsd : $rateUsd) * $effectiveEx;
        }

        return 0.0;
    }
}
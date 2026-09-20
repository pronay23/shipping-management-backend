<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    /** @use HasFactory<\Database\Factories\JournalEntryFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'voucher_number',
        'entry_date',
        'memo',
        'source_type',
        'source_id',
        'reverses_journal_entry_id',
        'total_debit',
        'total_credit',
        'status',
        'voided_at',
        'voided_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    /**
     * Get the lines that make up this journal entry.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Get the employee that created this entry.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Get the employee that last updated this entry.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by');
    }

    /**
     * Get the employee that voided this entry.
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'voided_by');
    }
}

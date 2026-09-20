<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    /** @use HasFactory<\Database\Factories\ItemFactory> */
    use HasFactory;

    protected $fillable = [
        'item_code',
        'item_name',
        'item_type',
        'uom_id',
        'item_prices',
        'item_taxes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'item_prices' => 'decimal:2',
        'item_taxes' => 'decimal:2',
    ];

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(Employee::class, 'updated_by');
    }
}

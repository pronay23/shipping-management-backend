<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillOfLading extends Model
{
    /** @use HasFactory<\Database\Factories\BillOfLadingFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the container manifests for the bill of lading.
     */
    public function containers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContainerManifest::class);
    }

    /**
     * Get the voyage that owns the bill of lading.
     */
    public function voyage(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    /** @use HasFactory<\Database\Factories\VendorFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_code',
        'vendor_name',
        'vendor_type',
        'vendor_category_id',
        'country_id',
        'currency_id',
        'contact_person',
        'email',
        'phone',
        'address',
        'tin_number',
        'vat_registration_number',
        'payment_terms',
        'credit_limit',
        'status',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
    ];

    public function vendorCategory()
    {
        return $this->belongsTo(VendorCategory::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}

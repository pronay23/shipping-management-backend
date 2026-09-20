<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voyage extends Model
{
    /** @use HasFactory<\Database\Factories\VoyageFactory> */
    use HasFactory;

    protected $fillable = [
        'vessel_name',
        'voyage_number',
        'port_of_loading',
        'port_of_discharge',
        'arrival_date',
        'departure_date',
        'status',
        'customs_office_code',
        'carrier_code',
        'carrier_name',
        'carrier_address',
        'mode_of_transport_code',
        'nationality_of_transporter_code',
    ];

    protected $casts = [
        'arrival_date' => 'date',
        'departure_date' => 'date',
    ];

    public function billOfLadings()
    {
        return $this->hasMany(BillOfLading::class);
    }
}

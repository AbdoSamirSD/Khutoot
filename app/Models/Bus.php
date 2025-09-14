<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    //

    protected $fillable = [
        'license_plate',
        'driver_id',
        'seating_capacity',
        'image',
        'created_at',
        'updated_at',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}

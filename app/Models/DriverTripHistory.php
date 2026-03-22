<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverTripHistory extends Model
{
    //

    protected $fillable = [
        'driver_id',
        'trip_id',
        'status',
        'created_at',
        'updated_at',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}

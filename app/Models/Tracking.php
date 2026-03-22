<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tracking extends Model
{
    //

    protected $fillable = [
        'trip_id',
        'current_station_id',
        'status',
        'last_updated',
    ];

    public $timestamps = false;

    public function tripInstance()
    {
        return $this->belongsTo(TripInstance::class);
    }

    public function currentStation()
    {
        return $this->belongsTo(Station::class, 'current_station_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteStation extends Model
{
    //

    protected $fillable = [
        'route_id',
        'station_id',
        'station_order',
        'arrival_date',
        'departure_date',
        'created_at',
        'updated_at',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function bookings(){
        return $this->hasMany(Booking::class);
    }
}

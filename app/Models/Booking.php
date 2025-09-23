<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    //

    protected $fillable = [
        'user_id',
        'trip_id',
        'seat_id',
        'status',
        'start_station_id',
        'end_station_id',
        'price',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tripInstance()
    {
        return $this->belongsTo(TripInstance::class);
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }

    // public function startStation()
    // {
    //     return $this->belongsTo(Station::class, 'start_station_id');
    // }

    // public function endStation()
    // {
    //     return $this->belongsTo(Station::class, 'end_station_id');
    // }

    public function routeStation()
    {
        return $this->belongsTo(RouteStation::class);
    }

    public function transaction()
    {
        return $this->hasOne(WalletUserTransaction::class);
    }

    public function ticket()
    {
        return $this->hasOne(Ticket::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripInstance extends Model
{
    protected $fillable = [
        'trip_id',
        'departure_time',
        'arrival_time',
        'status',
        'total_seats',
        'booked_seats',
        'available_seats',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    public function trackings()
    {
        return $this->hasMany(Tracking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function reports(){
        return $this->hasMany(Report::class);
    }
}

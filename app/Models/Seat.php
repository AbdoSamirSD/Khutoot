<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    //

    protected $fillable = [
        'bus_id',
        'seat_number',
        'is_booked',
        'created_at',
        'updated_at',
    ];

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function passengers()
    {
        return $this->belongsTo(User::class);
    }
}

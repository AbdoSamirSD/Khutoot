<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    //

    protected $fillable = [
        'booking_id',
        'ticket_number',
        'created_at',
        'updated_at',
        'status', // e.g., 'valid', 'cancelled', 'used'
        'seat_id',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
    
    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }
}

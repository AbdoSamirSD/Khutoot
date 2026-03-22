<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    //

    protected $fillable = [
        'bus_id',
        'driver_id',
        'route_id',
        'departure_time',
        'arrival_time',
        'available_seats',
        'price',
        'status',
        'created_at',
        'updated_at',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function tripInstances()
    {
        return $this->hasMany(TripInstance::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function reports(){
        return $this->hasMany(Report::class);
    }
}

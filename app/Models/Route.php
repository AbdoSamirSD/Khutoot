<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    //
    protected $fillable = [
        'name',
        'source',
        'destination',
        'created_at',
        'updated_at',
    ];

    public function trip()
    {
        return $this->hasMany(Trip::class);
    }

    public function stations()
    {
        return $this->belongsToMany(Station::class, 'route_stations')->withPivot(['station_order', 'arrival_time', 'departure_time']);
    }

    public function routeStations()
    {
        return $this->hasMany(RouteStation::class);
    }
}

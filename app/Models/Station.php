<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    //

    protected $fillable = [
        'name',
        'city',
        'created_at',
        'updated_at',
    ];

    public function routeStations()
    {
        return $this->hasMany(RouteStation::class);
    }

    public function routes()
    {
        return $this->belongsToMany(Route::class, 'route_stations')
                    ->withPivot(['station_order', 'arrival_time', 'departure_time']);
    }

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }


}

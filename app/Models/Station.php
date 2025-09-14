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

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }


}

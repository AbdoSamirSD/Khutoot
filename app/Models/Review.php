<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    //

    protected $fillable = [
        'user_id',
        'trip_id',
        'rating',
        'comment',
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
}

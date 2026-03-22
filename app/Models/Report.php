<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    //
    protected $fillable = [
        'report_number',
        'user_id',
        'trip_instance_id',
        'trip_id',
        'type',
        'description',
        'suggestions',
        'status',
        'admin_id',
        'admin_notes',
        'attachment',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function admin(){
        return $this->belongsTo(Admin::class);
    }

    public function trip(){
        return $this->belongsTo(Trip::class);
    }

    public function tripInstance(){
        return $this->belongsTo(TripInstance::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletDriver extends Model
{
    //

    protected $table = 'wallet_drivers';
    protected $fillable = [
        'driver_id',
        'balance',
        'created_at',
        'updated_at',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletDriverTransaction::class);
    }
}

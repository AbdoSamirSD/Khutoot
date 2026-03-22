<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletDriverTransaction extends Model
{
    //
    protected $table = 'wallet_drivers_transactions';

    protected $fillable = [
        'driver_wallet_id',
        'amount',
        'type',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function wallet()
    {
        return $this->belongsTo(WalletDriver::class);
    }
}

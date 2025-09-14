<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Driver extends Model
{
    //

    use HasApiTokens, HasFactory;
    protected $fillable = [
        'name',
        'license_number',
        'phone',
        'email',
        'picture',
        'password',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function bus()
    {
        return $this->hasOne(Bus::class);
    }

    public function tripHistories()
    {
        return $this->hasMany(DriverTripHistory::class);
    }

    public function wallet()
    {
        return $this->hasOne(WalletDriver::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletDriverTransaction::class);
    }
    public function trips()
    {
        return $this->hasOne(Trip::class);
    }
}

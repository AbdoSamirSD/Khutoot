<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletUser extends Model
{
    //

    protected $table = 'wallet_users';
    protected $fillable = [
        'user_id',
        'balance',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletUserTransaction::class, 'user_wallet_id');
    }

    public function payments(){
        return $this->hasMany(Payment::class);
    }
}

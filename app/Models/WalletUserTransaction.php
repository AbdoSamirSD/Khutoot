<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletUserTransaction extends Model
{
    //

    protected $table = 'wallet_users_transactions';
    protected $fillable = [
        'user_wallet_id',
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
        return $this->belongsTo(WalletUser::class);
    }
}

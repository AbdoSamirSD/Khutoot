<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    //
    protected $fillable = [
        'user_id',
        'amount',
        'payment_method',
        'status',
        'reference_number',
        'screenshot_path',
        'wallet_user_id'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function wallet(){
        return $this->belongsTo(WalletUser::class, 'wallet_user_id');
    }
}

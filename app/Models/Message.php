<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    //

    protected $fillable = [
        'chat_session_id',
        'sender_id',
        'sender_type',
        'message',
        'is_read',
        'created_at',
        'updated_at',
    ];

    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function sender()
    {
        return $this->morphTo();
    }
}

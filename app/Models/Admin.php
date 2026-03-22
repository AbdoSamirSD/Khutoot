<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{
    use HasFactory, HasApiTokens;
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function messages()
    {
        return $this->morphMany(Message::class, 'messageable');
    }
}
